<?php

namespace Simp\Core\extends\auto_path\src\path;

use NumberFormatter;
use Exception;
use Google\Service\Compute\Router;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\controllers\SystemController;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\structures\content_types\entity\Node;
use Simp\Core\modules\tokens\TokenManager;

class AutoPathAlias
{
    public function __construct(protected ?Database $database = null)
    {
    }

    protected function validateAlias(string $alias, string $entity_type): bool
    {
        if (is_null($this->database)) {
            return false;
        }
        $query = "SELECT * FROM auto_path_patterns WHERE pattern_path = :p AND entity_type = :entity_type";
        $query = $this->database->con()->prepare($query);
        $query->bindValue(':p', $alias);
        $query->bindValue(':entity_type', $entity_type);
        $query->execute();
        $count = $query->fetchColumn();
        return !empty($count);
    }

    public function addAlias(string $pattern, string $entity_type, string $default_route): bool
    {
        if (is_null($this->database)) {
            return false;
        }
        if (!$this->validateAlias($pattern, $entity_type)) {
            $query = "INSERT INTO auto_path_patterns (`pattern_path`, `entity_type`,`route_controller`) VALUES (:pattern_path, :entity_type, :route_controller)";
            $query = $this->database->con()->prepare($query);
            $query->bindValue(':pattern_path', $pattern);
            $query->bindValue(':entity_type', $entity_type);
            $query->bindValue(':route_controller', $default_route);
            return $query->execute();
        }
        return false;
    }

    public function getAliasByEntityType(string $entity_type): array|false|null
    {
        if (is_null($this->database)) {
            return false;
        }
        $query = "SELECT * FROM auto_path_patterns WHERE entity_type = :entity_type";
        $query = $this->database->con()->prepare($query);
        $query->bindValue(':entity_type', $entity_type);
        $query->execute();
        return $query->fetch();

    }

    public function getAliasByPattern(string $pattern): array|false|null
    {
        if (is_null($this->database)) {
            return false;
        }
        $query = "SELECT * FROM auto_path_patterns WHERE pattern_path = :pattern";
        $query = $this->database->con()->prepare($query);
        $query->bindValue(':pattern', $pattern);
        $query->execute();
        return $query->fetch();
    }

    public function deleteAlias(int $id): bool
    {
        if (is_null($this->database)) {
            return false;
        }
        $query = "DELETE FROM auto_path_patterns WHERE id = :id";
        $query = $this->database->con()->prepare($query);
        $query->bindValue(':id', $id);
        return $query->execute();
    }

    public function listAliases(): array
    {
        if (is_null($this->database)) {
            return [];
        }
        $query = "SELECT * FROM auto_path_patterns";
        $query = $this->database->con()->prepare($query);
        $query->execute();
        return $query->fetchAll();
    }

    protected function validatePath(string $path,int $pattern_id): bool
    {
        if (is_null($this->database)) {
            return false;
        }
        $query = "SELECT id FROM auto_path WHERE path = :path AND pattern_id = :pattern_id";
        $query = $this->database->con()->prepare($query);
        $query->bindValue(':path', $path);
        $query->bindValue(':pattern_id', $pattern_id);
        $query->execute();
        $count = $query->fetchColumn();
        return !empty($count);
    }

    /**
     * @throws Exception
     */
    public function __populate(): array
    {
        $patterns = $this->listAliases();

        $creation_happened = [];

        // now let loop through and get nodes that have not yet alias
        foreach ($patterns as $pattern) {
            $entity_type = $pattern['entity_type'];
            $pattern_id = (int) $pattern['id'];

            $query = "SELECT nid 
              FROM `node_data` 
              WHERE `bundle` = '{$entity_type}' 
              AND nid NOT IN (
                  SELECT nid 
                  FROM `auto_path` 
                  WHERE `pattern_id` = {$pattern_id}
              )";

            // Execute $query as needed
            $query = Database::database()->con()->prepare($query);
            $query->execute();
            $results = $query->fetchAll();

            if (count($results) > 0) {
                foreach ($results as $result) {
                    $node = Node::load($result['nid']);
                    $result_created = $this->create($node);
                    if ($result_created) {
                        $creation_happened['created'][] = true;
                    }
                    else {
                        $creation_happened['failed'][] = false;
                    }
                }
            }
        }
       return $creation_happened;
    }

    protected function createAliasUrl(string $token): string
    {
        // Trim whitespace
        $token = trim($token);

        // Convert to lowercase (optional but recommended for URLs)
        $token = strtolower($token);

        // Replace all non-alphanumeric characters with a dash
        $token = preg_replace('/[^a-z0-9]+/i', '-', $token);

        // Replace multiple dashes with a single dash
        $token = preg_replace('/-+/', '-', $token);

        // Remove starting and trailing dashes
        return trim($token, '-');
    }

    /**
     * @throws Exception
     */
    public function create(Node $node): bool
    {
        if (is_null($this->database)) {
            return false;
        }
        $token_manager = TokenManager::token();
        $data = $this->getAliasByEntityType($node->getEntityArray()['machine_name']) ?? [];
        if ($data) {
            $pattern = $data['pattern_path'];

            $appended = 0;
            while (true) {
                $list = explode('/', $pattern);
                foreach ($list as $key=>$token) {

                    if (str_starts_with($token, '[') && str_ends_with($pattern, ']')) {
                        while (true) {
                            $token = $token_manager->resolver($token, ['node' => $node]);
                            $token_url_part = $this->createAliasUrl($token);
                            if ($appended !== 0) {
                                $token_url_part .= "-".$appended;
                            }
                            if (!$this->validatePath($token_url_part, $data['id'])) {
                                $list[$key] = $token_url_part;
                                break;
                            }
                            $appended++;
                        }
                    }
                }

                $temp = implode('/', $list);
                $temp = "/" . trim($temp, '/');
                if (!$this->validatePath($temp, $data['id'])) {
                    $pattern = $temp;
                    break;
                }
                $appended += 1;
            }

            $query = "INSERT INTO auto_path (path, nid, pattern_id) VALUES (:path, :nid, :pattern_id)";
            $query = $this->database->con()->prepare($query);
            $query->bindValue(':path', $pattern);
            $query->bindValue(':nid', $node->getNid());
            $query->bindValue(':pattern_id', $data['id']);
            return $query->execute();
        }
        return false;
    }

    public static function createRouteId(int $path_id): string
    {
        $words = NumberFormatter::create('en_US', NumberFormatter::SPELLOUT)->format($path_id);
        $words = "auto.path.route.{$words}";

        // Trim whitespace
        $token = trim($words);

        // Convert to lowercase (optional but recommended for URLs)
        $token = strtolower($token);

        // Replace all non-alphanumeric characters with a dash
        $token = preg_replace('/[^a-z0-9]+/i', '.', $token);

        // Remove starting and trailing dashes
        return trim($token, '.');
    }

    public function isEntityTypeAutoPathEnabled(string $entity_type): bool
    {
        $pattern = $this->getAliasByEntityType($entity_type);
        return !empty($pattern);
    }

    public static function factory(?Database $database = null): AutoPathAlias
    {
        if (is_null($database)) {
            $database = Database::database();
        }
        return new self($database);
    }

    public function getPattern(int $id): array|false|null
    {
        $query = "SELECT * FROM auto_path_patterns WHERE id = :id";
        $query = $this->database->con()->prepare($query);
        $query->bindValue(':id', $id);
        $query->execute();
        return $query->fetch();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public  function getPaths(): array
    {
        if (is_null($this->database)) {
            return [];
        }
        $query = $this->database->con()->prepare("SELECT * FROM auto_path");
        $query->execute();
        $results = $query->fetchAll();
        $routes = [];
        foreach ($results as $result) {
            $route_key = self::createRouteId($result['nid']);
            $pattern = $this->getPattern($result['pattern_id']);
            $route_default = $pattern['route_controller'] ?? 'system.structure.content.node';
            $default_r = Route::fromRouteName($route_default);
            $access = [
                'administrator',
            ];
            if ($default_r) {
                $access = $default_r->getAccess();
            }
            $route = [
                'title' =>  $default_r?->route_title ?? 'Alias',
                'path' => $result['path'],
                'method' => $default_r?->method ??  [
                    'GET',
                    'POST',
                ],
                'controller' => [
                    'class' => SystemController::class,
                    'method' => 'content_node_controller'
                ],
                'access' => $access,
                'options' => [
                    'node' => $result['nid'],
                    'default' => $route_default,
                ]
            ];
            $routes[$route_key] = new Route($route_key, $route);
        }
        return $routes;
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function injectAliases(): array
    {
        return self::factory()->getPaths();
    }
}