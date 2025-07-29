<?php

namespace Simp\Core\modules\structures\content_types\entity;

use Exception;
use PDO;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\auto_path\src\path\AutoPathAlias;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\content_types\helper\NodeFunction;
use Simp\Core\modules\structures\content_types\storage\ContentDefinitionStorage;
use Simp\Core\modules\user\entity\User;
use Simp\Core\modules\user\trait\StaticHelperTrait;
use Throwable;

class Node
{
    use StaticHelperTrait;
    use NodeFunction;

    protected ?array $entity_types = [];
    protected array $values = [];

    public function __construct(
        protected ?int $nid,
        protected ?string $title,
        protected ?string $bundle,
        protected ?string $lang,
        protected ?int $status,
        protected ?string $created,
        protected ?string $updated,
        protected ?int $uid,
    )
    {
        $this->entity_types = ContentDefinitionManager::contentDefinitionManager()->getContentType($this->bundle) ?? [];
        $storage = ContentDefinitionStorage::contentDefinitionStorage($this->bundle)->getStorageJoinStatement();
        
        try{
            $query = Database::database()->con()->prepare($storage);
            $query->bindValue(':nid', $this->nid);
            $query->execute();
            $data = $query->fetchAll();
            $rows = [];

            foreach ($data as $value) {
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $rows[$k]['value'][] = $v;
                        $rows[$k]['value'] = array_unique($rows[$k]['value']);
                    }
                }
            }

            $this->values = $rows;
        }catch(Throwable){}
    }

    public static function filter(string $title, string $content_type): array
    {
        if (!empty($title) || !empty($content_type)) {
            $query = "SELECT nid FROM node_data WHERE bundle = :bundle AND title LIKE :title";
            $query = Database::database()->con()->prepare($query);
            $query->bindValue(':title', "%$title%");
            $query->bindValue(':bundle', $content_type);
            $query->execute();
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
            return array_map(fn($value) => Node::load($value['nid']), $result);
        }
        return [];
    }

    public function toArray(): array
    {
        return [
            'nid' => $this->nid,
            'title' => $this->title,
            'bundle' => $this->bundle,
            'status' => $this->status,
            'created' => $this->created,
            'updated' => $this->updated,
            ...$this->getValues()
        ];

    }

    public function getEntityArray(): ?array
    {
        return $this->entity_types;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function get(string $field_name)
    {
        $field = self::findField($this->entity_types['fields'], $field_name);
        if (empty($field)) {
            return null;
        }
        $values = $this->values[$field_name]['value'] ?? null;

        if (empty($values)) {
            return null;
        }

        if (!empty($field['limit']) && intval($field['limit']) === 1) {
            return [end($values)];
        }
        return $values;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getBundle(): ?string
    {
        return $this->bundle;
    }

    public function setBundle(?string $bundle): void
    {
        $this->bundle = $bundle;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(?string $lang): void
    {
        $this->lang = $lang;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(?int $status): void
    {
        $this->status = $status;
    }

    public function getCreated(): ?string
    {
        return $this->created;
    }

    public function setCreated(string $created): void
    {
        $this->created = $created;
    }

    public function getUpdated(): ?string
    {
        return $this->updated;
    }

    public function setUpdated(int $updated): void
    {
        $this->updated = $updated;
    }

    public function getNid(): ?int
    {
        return $this->nid;
    }

    public function setNid(?int $nid): void
    {
        $this->nid = $nid;
    }

    /**
     * @throws Exception
     */
    public static function create(array $data): ?Node
    {
        if (!empty($data['title']) && !empty($data['bundle']) && !empty($data['uid'])) {
            $data['lang'] = !empty($data['lang']) ? $data['lang'] : 'en';
            $connection = Database::database()->con();
            $query = "INSERT INTO node_data (title, bundle, status, uid, lang) VALUES (:title, :bundle, :status, :uid, :lang)";
            $query = $connection->prepare($query);
            $query->bindValue(':title', $data['title']);
            $query->bindValue(':bundle', $data['bundle']);
            $query->bindValue(':status', $data['status'] ?? 0);
            $query->bindValue(':uid', $data['uid']);
            $query->bindValue(':lang', $data['lang']);
            $query->execute();
            $nid = $connection->lastInsertId();
            if (!empty($nid)) {
                $node = Node::load($nid);
                // Add more field data.
                foreach ($data as $key => $value) {
                    $node->addFieldData($key, $value);
                }

                //Auto path creation if enabled
                if (ModuleHandler::factory()->isModuleEnabled('auto_path')) {
                    if (AutoPathAlias::factory()->isEntityTypeAutoPathEnabled($node->entity_types['machine_name'])) {
                        AutoPathAlias::factory()->create($node);
                    }
                }
                return Node::load($nid);
            }
        }
        return null;
    }

    public function __get(string $name)
    {
        return $this->$name;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(?int $uid): void
    {
        $this->uid = $uid;
    }

    public function getOwner(): ?User
    {
        return User::load($this->uid);
    }


    public function addFieldData(string $field_name,  $values): bool
    {
        $storage_query = ContentDefinitionStorage::contentDefinitionStorage($this->bundle)
        ->getStorageInsertStatement($field_name);

        if (!empty($storage_query)) {
            if (!is_array($values)) {
                $values = [$values];
            }

            $flags = [];
            foreach ($values as $value) {

                $query = Database::database()->con()->prepare($storage_query);
                $query->bindParam(':nid', $this->nid);
                $query->bindParam(':field_value', $value);
                $flags[]= $query->execute();
            }
            return !in_array(false, $flags);

        }
        return false;
    }

    public function updateFieldData(string $field_name,  $values): bool
    {
        $storage_query = ContentDefinitionStorage::contentDefinitionStorage($this->bundle)
            ->getStorageUpdateStatement($field_name);

        $storage_query1 = ContentDefinitionStorage::contentDefinitionStorage($this->bundle)
            ->getStorageSelectStatement($field_name);

        $storage_query2 = ContentDefinitionStorage::contentDefinitionStorage($this->bundle)
            ->getStorageInsertStatement($field_name);

        if (!empty($storage_query) && !empty($storage_query1) && !empty($storage_query2)) {

            if (!is_array($values)) {
                $values = [$values];
            }

            $flags = [];
            foreach ($values as $value) {

                // First check if we have data.
                $query = Database::database()->con()->prepare($storage_query1);
                $query->bindParam(':nid', $this->nid);
                $query->bindParam(':field_value', $value);
                $query->execute();
                $data = $query->fetch();
                if (!empty($data)) {
                    $query = Database::database()->con()->prepare($storage_query);
                }
                else {
                   $query = Database::database()->con()->prepare($storage_query2);
                }
                $query->bindParam(':nid', $this->nid);
                $query->bindParam(':field_value', $value);
                $flags[]= $query->execute();

            }
            return !in_array(false, $flags);

        }
        return false;
    }

    public static function load(int $nid): ?Node {
        $connection = Database::database()->con();
        $query = "SELECT * FROM node_data WHERE nid = :nid";
        $query = $connection->prepare($query);
        $query->bindValue(':nid', $nid);
        $query->execute();
        $result = $query->fetch();
        if (empty($result)) {
            return null;
        }
        return new Node(...$result);
    }

    public static function loadByType(string $type): array
    {
        $connection = Database::database()->con();
        $query = "SELECT * FROM node_data WHERE bundle = :bundle ORDER BY updated";
        $query = $connection->prepare($query);
        $query->bindValue(':bundle', $type);
        $query->execute();
        $result = $query->fetchAll();
        if (empty($result)) {
            return [];
        }
        return array_map(fn($value) => new Node(...$value), $result);
    }

    public static function loadByTypes(array $types): array
    {
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $connection = Database::database()->con();
        $query = "SELECT * FROM node_data WHERE bundle IN ($placeholders) ORDER BY created DESC";
        $statement = $connection->prepare($query);
        foreach ($types as $key => $value) {
            $statement->bindValue($key + 1, $value);
        }
        $statement->execute();

        $result = $statement->fetchAll();
        if (empty($result)) {
            return [];
        }
        return array_map(fn($value) => new Node(...$value), $result);
    }

    public static function loadByOwner(int $uid, ?string $bundle): array
    {
        $query = "SELECT * FROM node_data WHERE uid = :uid";
        if ($bundle) {
            $query .= " AND bundle = :bundle";
        }
        $query = Database::database()->con()->prepare($query);
        $query->bindValue(':uid', $uid);
        if ($bundle) {
            $query->bindValue(':bundle', $bundle);
        }
        $query->execute();
        $result = $query->fetchAll();
        if (empty($result)) {
            return [];
        }
        return array_map(fn($value) => new Node(...$value), $result);
    }

    public function __toString(): string
    {
        $top_table = [
            'title' => $this->title,
            'bundle' => $this->bundle,
            'status' => $this->status,
            'created' => $this->created,
            'updated' => $this->updated,
            'owner' => $this->getOwner()->toArray(),
            ...$this->getValues()
        ];

        return json_encode($top_table, JSON_PRETTY_PRINT);
    }

    public function update(array $other_fields): bool|Node|null
    {
        $connection = Database::database()->con();
        $query = "UPDATE node_data SET title = :title, status = :status, uid = :uid WHERE nid = :nid";
        $query = $connection->prepare($query);
        $query->bindValue(':title', $this->title);
        $query->bindValue(':status', $this->status);
        $query->bindValue(':uid', $this->uid);
        $query->bindValue(':nid', $this->nid);
        if ($query->execute()) {
            foreach ($other_fields as $key => $value) {
               $this->updateFieldData($key, $value);
            }
            return  self::load($this->nid);
        }
        return false;
    }

    public function delete(int $action = 1): bool {
        $connection = Database::database()->con();
        $query = "DELETE FROM node_data WHERE nid = :nid";
        if ($action == 1) {
            $query = $connection->prepare($query);
        }
        else {
            $query = "UPDATE node_data SET status = :status WHERE nid = :nid";
            $query = $connection->prepare($query);
            $query->bindValue(':status', 0);
        }
        $query->bindValue(':nid', $this->nid);
        return $query->execute();
    }

}