<?php

namespace Simp\Core\modules\structures\content_types\helper;
use PDO;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\files\helpers\FileFunction;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\content_types\entity\Node;

trait NodeFunction
{
    public static function findField(array $fields, string $field_name)
    {
        foreach ($fields as $key=>$field) {
            if ($field_name == $key) {
                return $field;
            }
            elseif (isset($field['inner_field'])) {
                $found = self::findField($field['inner_field'], $field_name);
                if ($found) {
                    return $found;
                }
            }
        }
        return [];
    }

    public function nodeFile(array|int $fid, bool $webroot = true): array
    {
        if (is_array($fid)) {
            $uri = [];
            foreach ($fid as $f) {
                $uri[] = FileFunction::reserve_uri(FileFunction::resolve_fid($f),$webroot);
            }
            return $uri;
        }
        return [FileFunction::reserve_uri(FileFunction::resolve_fid($fid),$webroot)];
    }

    public function nodeFileContent(array|int $fid, bool $webroot = true): array
    {
        if (empty($fid)) {
            return [];
        }
        $content = [];
        if (is_numeric($fid)) {
            $fid = [$fid];
        }

        foreach ($fid as $f) {
            if (!empty($f)) {
                $file = File::load($f);

                if(!$file) {
                    return [];
                }
                $content[] = [
                    'name' => $file->getName(),
                    'size' => $file->getSize(),
                    'type' => $file->getMimeType(),
                    'uri' => FileFunction::reserve_uri(FileFunction::resolve_fid($f),$webroot),
                    'is_image' => str_starts_with($file->getMimeType(), 'image'),
                ];
            }
        }
        return $content;
    }

    public static function referenceEntities(array $value): array
    {
        $reference_entities = [];
        $content_types = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
        $fields = [];
        foreach ($content_types as $content_type) {
            $fields = array_merge($fields, $content_type['fields']);
        }

        $function = function ($fields, &$reference_entities) use (&$function): void {
            foreach ($fields as $field) {
                if ($field['type'] === 'reference') {
                    $reference_entities[] = $field['name'];
                }
                if (!empty($field['inner_field'])) {
                    $function($field['inner_field'], $reference_entities);
                }
            }
        };

        $function($fields, $reference_entities);

        if (!empty($reference_entities)) {
            $reference_entities = array_unique($reference_entities);

            // Create table and field names
            $tables = array_map(fn($ref) => "node__{$ref}", $reference_entities);
            $where_clause = array_map(fn($ref) => "{$ref}__value", $reference_entities);

            $base_table = 'node_data';
            $join_statements = '';
            $where_conditions = [];
            $params = [];

            foreach ($tables as $index => $table) {
                $alias = "t{$index}";
                $join_column = (count($tables) === 1) ? 'nid' : 'nid';

                $join_statements .= "LEFT JOIN {$table} AS {$alias} ON {$alias}.{$join_column} = {$base_table}.nid\n";

                $field = $where_clause[$index];
                $field_values = $value; // You may need to adapt if $value is per-field

                $placeholders = [];
                foreach ($field_values as $i => $val) {
                    $param_key = "{$field}_{$i}";
                    $placeholders[] = ":{$param_key}";
                    $params[$param_key] = $val;
                }

                if (!empty($placeholders)) {
                    $where_conditions[] = "{$alias}.{$field} IN (" . implode(', ', $placeholders) . ")";
                }
            }

            // Build SELECT statement
            $select = "SELECT {$base_table}.nid FROM {$base_table} \n" . $join_statements;


            if (!empty($where_conditions)) {
                $select .= "WHERE (" . implode(" OR ", $where_conditions) . ") ";
            } else {
                $select .= "WHERE 1=1 ";
            }

            $select .= "AND {$base_table}.status = 1 GROUP BY node_data.nid ORDER BY {$base_table}.updated DESC";

            // Execute query
            $query = Database::database()->con()->prepare($select);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_ASSOC);

            // Optionally return or use $results
            return array_map(function ($nid){ return Node::load($nid['nid']); }, $results);
        }
        return [];
    }

}