<?php


namespace Simp\Core\extends\auto_path\src\path;

use NumberFormatter;
use Exception;
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
    protected string $cache_key = 'auto_path_patterns_cache_key';
    protected string $cache_key_path = 'auto_path_patterns_cache_key_path';

    protected array $patterns = [];
    protected array $paths = [];

    public function __construct(protected ?Database $database = null)
    {
        if (!is_null($this->database)) {
            if (!Caching::init()->has($this->cache_key)) {
                $stmt = $this->database->con()->prepare("SELECT * FROM auto_path_patterns");
                $stmt->execute();
                Caching::init()->set($this->cache_key, $stmt->fetchAll());

                $stmt = $this->database->con()->prepare("SELECT * FROM auto_path");
                $stmt->execute();
                Caching::init()->set($this->cache_key_path, $stmt->fetchAll());
            }
        }

        $this->patterns = Caching::init()->get($this->cache_key) ?? [];
        $this->paths = Caching::init()->get($this->cache_key_path) ?? [];
    }

    protected function validateAlias(string $alias, string $entity_type): bool
    {
        foreach ($this->patterns as $pattern) {
            if ($pattern['pattern_path'] === $alias && $pattern['entity_type'] === $entity_type) {
                return true;
            }
        }
        return false;
    }

    public function addAlias(string $pattern, string $entity_type, string $default_route): bool
    {
        if (is_null($this->database) || $this->validateAlias($pattern, $entity_type)) {
            return false;
        }

        $query = "INSERT INTO auto_path_patterns (`pattern_path`, `entity_type`, `route_controller`) VALUES (:pattern_path, :entity_type, :route_controller)";
        $stmt = $this->database->con()->prepare($query);
        $stmt->bindValue(':pattern_path', $pattern);
        $stmt->bindValue(':entity_type', $entity_type);
        $stmt->bindValue(':route_controller', $default_route);
        $result = $stmt->execute();

        if ($result) {
            $this->patterns[] = [
                    'id' => $this->database->con()->lastInsertId(),
                    'pattern_path' => $pattern,
                    'entity_type' => $entity_type,
                    'route_controller' => $default_route
            ];
            Caching::init()->set($this->cache_key, $this->patterns);
        }

        return $result;
    }

    public function getAliasByEntityType(string $entity_type): array|false|null
    {
        foreach ($this->patterns as $pattern) {
            if ($pattern['entity_type'] === $entity_type) {
                return $pattern;
            }
        }
        return null;
    }

    public function getAliasByPattern(string $pattern): array|false|null
    {
        foreach ($this->patterns as $p) {
            if ($p['pattern_path'] === $pattern) {
                return $p;
            }
        }
        return null;
    }

    public function deleteAlias(int $id): bool
    {
        if (is_null($this->database)) return false;

        $query = "DELETE FROM auto_path_patterns WHERE id = :id";
        $stmt = $this->database->con()->prepare($query);
        $stmt->bindValue(':id', $id);
        $result = $stmt->execute();

        if ($result) {
            $this->patterns = array_filter($this->patterns, fn($p) => $p['id'] !== $id);
            Caching::init()->set($this->cache_key, $this->patterns);
        }

        return $result;
    }

    public function listAliases(): array
    {
        return $this->patterns;
    }

    protected function validatePath(string $path, int $pattern_id): bool
    {
        foreach ($this->paths as $p) {
            if ($p['path'] === $path && $p['pattern_id'] === $pattern_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public function __populate(): array
    {
        $creation_happened = [];

        foreach ($this->patterns as $pattern) {
            $entity_type = $pattern['entity_type'];
            $pattern_id = (int)$pattern['id'];

            // Load all nodes of this entity type (custom implementation for non-Drupal)
            $all_nodes = Node::loadByEntityType($entity_type);

            foreach ($all_nodes as $node) {
                $nid = $node->getNid();
                $exists = false;

                foreach ($this->paths as $p) {
                    if ($p['nid'] === $nid && $p['pattern_id'] === $pattern_id) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $result_created = $this->create($node);
                    if ($result_created) {
                        $creation_happened['created'][] = $nid;
                    } else {
                        $creation_happened['failed'][] = $nid;
                    }
                }
            }
        }

        return $creation_happened;
    }

    protected function createAliasUrl(string $token): string
    {
        $token = strtolower(trim($token));
        $token = preg_replace('/[^a-z0-9]+/i', '-', $token);
        $token = preg_replace('/-+/', '-', $token);
        return trim($token, '-');
    }

    /**
     * @throws Exception
     */
    public function create(Node $node): bool
    {
        if (is_null($this->database)) return false;

        $token_manager = TokenManager::token();
        $data = $this->getAliasByEntityType($node->getEntityArray()['machine_name']);
        if (!$data) return false;

        $pattern = $data['pattern_path'];
        $appended = 0;

        while (true) {
            $list = explode('/', $pattern);

            foreach ($list as $key => $token) {
                if (str_starts_with($token, '[') && str_ends_with($token, ']')) {
                    while (true) {
                        $token_val = $token_manager->resolver($token, ['node' => $node]);
                        $token_url_part = $this->createAliasUrl($token_val);
                        if ($appended !== 0) $token_url_part .= "-" . $appended;
                        if (!$this->validatePath($token_url_part, $data['id'])) {
                            $list[$key] = $token_url_part;
                            break;
                        }
                        $appended++;
                    }
                }
            }

            $temp = "/" . trim(implode('/', $list), '/');
            if (!$this->validatePath($temp, $data['id'])) {
                $pattern = $temp;
                break;
            }
            $appended++;
        }

        $query = "INSERT INTO auto_path (path, nid, pattern_id) VALUES (:path, :nid, :pattern_id)";
        $stmt = $this->database->con()->prepare($query);
        $stmt->bindValue(':path', $pattern);
        $stmt->bindValue(':nid', $node->getNid());
        $stmt->bindValue(':pattern_id', $data['id']);
        $result = $stmt->execute();

        if ($result) {
            $this->paths[] = [
                    'id' => $this->database->con()->lastInsertId(),
                    'path' => $pattern,
                    'nid' => $node->getNid(),
                    'pattern_id' => $data['id'],
                    'created_at' => date('Y-m-d H:i:s')
            ];
            Caching::init()->set($this->cache_key_path, $this->paths);
        }

        return $result;
    }

    public static function createRouteId(int $path_id): string
    {
        return "auto.path.route." . $path_id;
    }

    public function isEntityTypeAutoPathEnabled(string $entity_type): bool
    {
        return !empty($this->getAliasByEntityType($entity_type));
    }

    public static function factory(?Database $database = null): AutoPathAlias
    {
        if (is_null($database)) {
            $database = Database::database();
        }
        return new self($database);
    }

    public function getPattern(int $id): array|null
    {
        foreach ($this->patterns as $p) {
            if ($p['id'] === $id) return $p;
        }
        return null;
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getPaths(): array
    {
        $routes = [];
        foreach ($this->paths as $result) {
            $route_key = self::createRouteId($result['nid']);
            $pattern = $this->getPattern($result['pattern_id']);
            $route_default = $pattern['route_controller'] ?? 'system.structure.content.node';
            $default_r = Route::fromRouteName($route_default);
            $access = $default_r?->getAccess() ?? ['administrator'];

            $routes[$route_key] = new Route($route_key, [
                    'title' => $default_r?->route_title ?? 'Alias',
                    'path' => $result['path'],
                    'method' => $default_r?->method ?? ['GET', 'POST'],
                    'controller' => [
                            'class' => SystemController::class,
                            'method' => 'content_node_controller'
                    ],
                    'access' => $access,
                    'options' => [
                            'node' => $result['nid'],
                            'default' => $route_default,
                    ]
            ]);
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
