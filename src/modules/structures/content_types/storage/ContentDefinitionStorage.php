<?php

namespace Simp\Core\modules\structures\content_types\storage;

use Throwable;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\logger\ErrorLogger;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;

class ContentDefinitionStorage
{
    protected ?array $content_type;
    public function __construct(protected string $content_name)
    {
        $this->content_type = ContentDefinitionManager::contentDefinitionManager()->getContentType($content_name);
    }

    public function storageDefinitionsPersistent(): void
    {
        $fields = $this->content_type['fields'] ?? [];
        $created_tables = $this->content_type['storage'] ?? [];

        // create a table and map it to the content type;
        foreach ($fields as $key => $field) {
            try{

                if (!empty($field['inner_field'])) {
                    $this->createTable($field['inner_field'], $created_tables);
                }
                else {
                    $this->createTable([$key => $field], $created_tables);
                }

            }catch (Throwable $e) {
                ErrorLogger::logger()->logError($e->getMessage().' in '.$e->getFile().' on line '.$e->getLine().'\n'.PHP_EOL.$e->getTraceAsString());
            }
        }

        $this->content_type['storage'] = array_unique($created_tables);
        ContentDefinitionManager::contentDefinitionManager()->addContentType(
            $this->content_name,
            $this->content_type,
        );
    }

    protected function createTable(array $field_config, array &$created_tables): bool {
       try{

           foreach ($field_config as $key => $field) {
               $type = $field['type'] ?? null;
               if ($type === 'number') {
                   $type = "INT";
               } elseif ($type === 'textarea') {
                   $type = "TEXT";
               } else {
                   $type = "VARCHAR(255)";
               }

               if (!empty($key)) {
                   $entity_field = "`nid` INT NOT NULL";
                   $constraint = "CONSTRAINT `fk_node__{$key}_nid` FOREIGN KEY (`nid`) REFERENCES `node_data` (`nid`) ON DELETE CASCADE";
                   $required = !empty($field['required']) ? "NOT NULL" : "NULL";
                   $default = !empty($field['default_value']) ? "DEFAULT '" . $field['default_value'] . "'" : "NULL";
                   $comment = !empty($field['description']) ? "COMMENT '" . $field['description'] . "'" : "NULL";
                   $line = "CREATE TABLE IF NOT EXISTS `node__{$key}` (`{$key}_id` INT PRIMARY KEY AUTO_INCREMENT, $entity_field, `{$key}__value` {$type} $required {$default} {$comment}, $constraint)";
                   $query = Database::database()->con()->prepare($line);
                   if ($query->execute()) {
                       $created_tables[] = "node__" . $key;
                   }
               }
           }
       }catch (Throwable $e) {
           ErrorLogger::logger()->logError($e->getMessage().' in '.$e->getFile().' on line '.$e->getLine().'\n'.PHP_EOL.$e->getTraceAsString());
       }
       return true;
    }

    public function getStorageDefinition(string $field_name): ?string
    {
        return $this->content_type['storage']["node__{$field_name}"] ?? null;
    }

    public function removeStorageDefinition(string $field_name): bool
    {
        $index = array_search("node__{$field_name}", $this->content_type['storage']);
        if ($index !== false) {
            unset($this->content_type['storage'][$index]);
            ContentDefinitionManager::contentDefinitionManager()->addContentType(
                $this->content_name,
                $this->content_type,
            );
            $query = "DROP TABLE IF EXISTS `node__{$field_name}`";
            $query = Database::database()->con()->prepare($query);
            return $query->execute();
        }
        return false;
    }

    public function getStorageJoinStatement(): ?string
    {
        $tables = $this->content_type['storage'] ?? [];
        $joins = [];
        $columns = [];

        foreach ($tables as $key => $table) {
            $name = substr($table, 5);  // Trim the prefix
            $name = trim($name, '_');
            $alias = "P$key";

            // Select the value field as-is, without concatenation
            $columns[] = "{$alias}.{$name}__value AS {$name}";
            $joins[] = "LEFT JOIN `$table` $alias ON N.nid = $alias.nid";
        }

        $cols = implode(', ', $columns);
        $joinsString = implode(' ', $joins);

        return "SELECT {$cols} FROM `node_data` N {$joinsString} WHERE N.nid = :nid";
    }


    public function getStorageInsertStatement(string $field_name): ?string
    {
        if (empty($this->content_type['storage'])) return null;
        $index = array_search("node__{$field_name}", $this->content_type['storage']);
        if ($index !== false) {
            $name = substr($this->content_type['storage'][$index], 4, strlen($this->content_type['storage'][$index]));
            $name = trim($name, '_');
            return "INSERT INTO `node__{$name}` (`nid`, `{$name}__value`) VALUES (:nid, :field_value)";
        }
        return null;
    }

    public function getStorageUpdateStatement(string $field_name): ?string
    {
        $index = array_search("node__{$field_name}", $this->content_type['storage']);
        if ($index !== false) {
            $name = substr($this->content_type['storage'][$index], 4, strlen($this->content_type['storage'][$index]));
            $name = trim($name, '_');
            return "UPDATE `node__{$name}` SET `{$name}__value` = :field_value WHERE `nid` = :nid";
        }
        return null;
    }

    public function getStorageDeleteStatement(string $field_name): ?string
    {
        $index = array_search("node__{$field_name}", $this->content_type['storage']);
        if ($index !== false) {
            $name = substr($this->content_type['storage'][$index], 4, strlen($this->content_type['storage'][$index]));
            $name = trim($name, '_');
            return "DELETE FROM `node__{$name}` WHERE `nid` = :nid";
        }
        return null;
    }

    public function getStorageDropStatement(string $field_name): ?string
    {

        $index = array_search("node__{$field_name}", $this->content_type['storage']);
        if ($index !== false) {
            $name = substr($this->content_type['storage'][$index], 4, strlen($this->content_type['storage'][$index]));
            $name = trim($name, '_');
            return "DROP TABLE `node__{$name}`";
        }
        return null;
    }

    public static function contentDefinitionStorage(string $content_name): ContentDefinitionStorage
    {
        return new self($content_name);
    }

    public function getStorageSelectStatement(string $field_name): ?string
    {
        $index = array_search("node__{$field_name}", $this->content_type['storage']);
        if ($index !== false) {
            $name = substr($this->content_type['storage'][$index], 4, strlen($this->content_type['storage'][$index]));
            $name = trim($name, '_');
            return "SELECT * FROM `node__{$name}` WHERE `nid` = :nid AND {$field_name}__value = :field_value";
        }
        return null;
    }
}
