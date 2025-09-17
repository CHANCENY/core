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
                ErrorLogger::logger()->logError($e);
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
                   $type = "LONGTEXT";
               } else {
                   $type = "VARCHAR(500)";
               }

               if ($key !== 0 && ($key !== '' && $key !== '0')) {
                   $entity_field = "`nid` INT NOT NULL";
                   $constraint = sprintf('CONSTRAINT `fk_node__%s_nid` FOREIGN KEY (`nid`) REFERENCES `node_data` (`nid`) ON DELETE CASCADE', $key);
                   $required = empty($field['required']) ? "NULL" : "NOT NULL";
                   $default = empty($field['default_value']) ? "NULL" : "DEFAULT '" . $field['default_value'] . "'";
                   $comment = empty($field['description']) ? "NULL" : "COMMENT '" . $field['description'] . "'";
                   $line = sprintf('CREATE TABLE IF NOT EXISTS `node__%s` (`%s_id` INT PRIMARY KEY AUTO_INCREMENT, %s, `%s__value` %s %s %s %s, %s)', $key, $key, $entity_field, $key, $type, $required, $default, $comment, $constraint);
                   $query = Database::database()->con()->prepare($line);
                   if ($query->execute()) {
                       $created_tables[] = "node__" . $key;
                   }
               }
           }
       }catch (Throwable $throwable) {
           ErrorLogger::logger()->logError($throwable);
       }

       return true;
    }

    public function getStorageDefinition(string $field_name): ?string
    {
        return $this->content_type['storage']['node__' . $field_name] ?? null;
    }

    public function removeStorageDefinition(string $field_name): bool
    {
        $index = array_search('node__' . $field_name, $this->content_type['storage'], true);
        if ($index !== false) {
            unset($this->content_type['storage'][$index]);
            ContentDefinitionManager::contentDefinitionManager()->addContentType(
                $this->content_name,
                $this->content_type,
            );
            $query = sprintf('DROP TABLE IF EXISTS `node__%s`', $field_name);
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
            $name = substr((string) $table, 5);  // Trim the prefix
            $name = trim($name, '_');
            $alias = 'P' . $key;

            // Select the value field as-is, without concatenation
            $columns[] = sprintf('%s.%s__value AS %s', $alias, $name, $name);
            $joins[] = sprintf('LEFT JOIN `%s` %s ON N.nid = %s.nid', $table, $alias, $alias);
        }

        $cols = implode(', ', $columns);
        $joinsString = implode(' ', $joins);

        return sprintf('SELECT %s FROM `node_data` N %s WHERE N.nid = :nid', $cols, $joinsString);
    }


    public function getStorageInsertStatement(string $field_name): ?string
    {
        if (empty($this->content_type['storage'])) {
            return null;
        }

        $index = array_search('node__' . $field_name, $this->content_type['storage'], true);
        if ($index !== false) {
            $name = substr((string) $this->content_type['storage'][$index], 4, strlen((string) $this->content_type['storage'][$index]));
            $name = trim($name, '_');
            return sprintf('INSERT INTO `node__%s` (`nid`, `%s__value`) VALUES (:nid, :field_value)', $name, $name);
        }

        return null;
    }

    public function getStorageUpdateStatement(string $field_name): ?string
    {
        $index = array_search('node__' . $field_name, $this->content_type['storage'], true);
        if ($index !== false) {
            $name = substr((string) $this->content_type['storage'][$index], 4, strlen((string) $this->content_type['storage'][$index]));
            $name = trim($name, '_');
            return sprintf('UPDATE `node__%s` SET `%s__value` = :field_value WHERE `nid` = :nid', $name, $name);
        }

        return null;
    }

    public function getStorageDeleteStatement(string $field_name): ?string
    {
        $index = array_search('node__' . $field_name, $this->content_type['storage'], true);
        if ($index !== false) {
            $name = substr((string) $this->content_type['storage'][$index], 4, strlen((string) $this->content_type['storage'][$index]));
            $name = trim($name, '_');
            return sprintf('DELETE FROM `node__%s` WHERE `nid` = :nid', $name);
        }

        return null;
    }

    public function getStorageDropStatement(string $field_name): ?string
    {

        $index = array_search('node__' . $field_name, $this->content_type['storage'], true);
        if ($index !== false) {
            $name = substr((string) $this->content_type['storage'][$index], 4, strlen((string) $this->content_type['storage'][$index]));
            $name = trim($name, '_');
            return sprintf('DROP TABLE `node__%s`', $name);
        }

        return null;
    }

    public static function contentDefinitionStorage(string $content_name): ContentDefinitionStorage
    {
        return new self($content_name);
    }

    public function getStorageSelectStatement(string $field_name): ?string
    {
        $index = array_search('node__' . $field_name, $this->content_type['storage'], true);
        if ($index !== false) {
            $name = substr((string) $this->content_type['storage'][$index], 4, strlen((string) $this->content_type['storage'][$index]));
            $name = trim($name, '_');
            return sprintf('SELECT * FROM `node__%s` WHERE `nid` = :nid AND %s__value = :field_value', $name, $field_name);
        }

        return null;
    }
}
