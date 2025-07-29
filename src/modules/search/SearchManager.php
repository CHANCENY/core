<?php

namespace Simp\Core\modules\search;

use PDO;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\content_types\helper\NodeFunction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class SearchManager
{

    protected array $settings = [];
    protected string $location = '';
    private string $search_query;
    private array $placeholders = [];
    private array $results;
    private array $database_queries = [];

    public function __construct()
    {
        $system = new SystemDirectory();
        $search_config = $system->setting_dir . DIRECTORY_SEPARATOR . 'config';
        if (!is_dir($search_config)) {
            mkdir($search_config);
        }
        $search_config .=  DIRECTORY_SEPARATOR . 'search';
        if (!is_dir($search_config)) {
            mkdir($search_config);
        }
        $search_config .= DIRECTORY_SEPARATOR . 'search.yml';
        if (!file_exists($search_config)) {
            @touch($search_config);
        }

        $defaults = Caching::init()->get('default.admin.search.setting');
        if (!empty($defaults) && file_exists($defaults)) {
            $defaults = Yaml::parseFile($defaults) ?? [];
        }

        $custom = Yaml::parseFile($search_config) ?? [];
        $this->settings = array_merge($defaults, $custom);
        $this->location = $search_config;
    }

    public function addSetting(string $key, array $value): bool
    {
        $this->settings[$key] = $value;
        return !empty(file_put_contents($this->location, Yaml::dump($this->settings,Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)));

    }

    public function getSettings(): array
    {
        return $this->settings;
    }
    public function getLocation(): string {
        return $this->location;
    }

    public function getSetting(string $key) {
        return $this->settings[$key] ?? null;
    }

    public function removeSetting(string $key): bool
    {
        if (isset($this->settings[$key])) {
            unset($this->settings[$key]);
            return !empty(file_put_contents($this->location, Yaml::dump($this->settings,Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)));
        }
        return false;
    }

    public function getSourceSearchableField(string $key): array
    {
        $setting  = $this->getSetting($key);

        if ($setting) {
            if (isset($setting['type']) && $setting['type'] == 'content_type') {
                $fields = [];
                if (isset($setting['sources'])) {
                    foreach ($setting['sources'] as $source) {
                        $fields = array_merge($fields, $this->getContentTypeFields($source));
                    }
                }
                $fields = array_combine(array_values($fields), array_values($fields));
                return [
                    'node_data:title' => 'Title',
                    'node_data:uid' => 'Author',
                    'node_data:created' => 'Authored on',
                    'node_data:updated' => 'Updated on',
                    'node_data:nid' => 'Node ID',
                    'node_data:lang' => 'Language',
                    ...$fields
                ];
            }
            elseif (isset($setting['type']) && $setting['type'] == 'user_type') {
                return [
                    'users:uid' => 'User ID',
                    'users:name' => 'Username',
                    'users:created' => 'Created on',
                    'users:updated' => 'Updated on',
                    'user_profile:first_name' => 'First Name',
                    'user_profile:last_name' => 'Last Name',
                    'user_profile:description' => 'Profile Description',
                    'user_profile:time_zone' => 'Time Zone',
                    'user_profile:profile_image' => 'Profile Image',
                ];
            }
            elseif (isset($setting['type']) && $setting['type'] == 'database_type') {
                $query = Database::database()->con()->prepare("SHOW TABLES");
                $query->execute();
                $rows = $query->fetchAll(PDO::FETCH_COLUMN);
                $new_rows = array_filter($rows, function ($row) {
                    $excludes = ['users', 'user_profile', 'user_roles', 'file_managed', 'node_data'];
                    return !in_array($row, $excludes);
                });
                return array_combine(array_values($new_rows), array_values($new_rows));
            }
        }
        return [];
    }

    public function getDatabaseSearchableColumns(string $source): array
    {
        $query = Database::database()->con()->prepare("SHOW COLUMNS FROM $source");
        $query->execute();
        $rows = $query->fetchAll(PDO::FETCH_COLUMN);
        $new_rows = array_map(function ($row) use ($source) {
            return "$source:$row";
        },$rows);
        return array_combine(array_values($new_rows), array_values($new_rows));
    }

    protected function getContentTypeFields(string $source): array
    {
        $fields = ContentDefinitionManager::contentDefinitionManager()->getContentType($source);
        $storages = $fields['storage'] ?? [];
        return array_map(function ($field) use ($source) {
            return $source . ":". substr($field,6, strlen($field));
        },$storages);
    }

    public function buildSearchQuery(string $key, Request $request): string|array|null
    {
        $definition = $this->getSetting($key);
        if ($definition) {

            if (isset($definition['type']) && $definition['type'] == 'content_type') {

                $select_part = array_map(function ($field) {
                    $list = explode(':', $field);
                    return $list[0] !== 'node_data' ? "node__{$list[1]}.{$list[1]}__value AS {$list[1]}" : "node_data.{$list[1]} AS {$list[1]}";
                }, $definition['fields']);

                $tables = array_map(function ($field) {
                    $list = explode(':', $field);
                    return $list[0] !== 'node_data' ? "node__{$list[1]}" : $list[0];
                },$definition['fields']);

                $tables = array_unique($tables);
                $tables = array_merge(['node_data'], $tables);
                $tables = array_unique($tables);

                // SELECT part of query
                $join_statement = "SELECT ".implode(", ", $select_part);

                // Join tables
                $tables_part = null;
                $others = [];
                foreach ($tables as $key=>$table) {
                    if ($key === 0) {
                        $tables_part = "`$table` ";
                    }
                    else {
                        $others[] = "`$table` ON {$table}.nid = node_data.nid";
                    }
                }

                if (count($others) >= 1) {
                    $join_statement .= " FROM ". $tables_part ." INNER JOIN" . implode(" INNER JOIN ", $others);
                }else {
                    $join_statement .= " FROM ". $tables_part;
                }

                // Where part
                $bundles = array_map(function ($source) {
                    return "'{$source}'";
                },$definition['sources']);

                $join_statement .= " WHERE node_data.bundle IN (" . implode(", ", $bundles) . ")";
                $search_fields = [];

                foreach ($definition['filter_definitions'] as $key => $value) {

                    if (array_key_exists($key, $definition['exposed'] ?? []) && $definition['exposed'][$key] === true) {
                        $list = explode(':', $key);
                        $table = $list[0] !== 'node_data' ? "node__{$list[1]}" : $list[0];
                        $placeholder = $list[1];
                        $this->placeholders[] = $key;

                        if ($value === 'contains' || $value === 'starts_with' || $value === 'ends_with') {
                            $search_fields[] = "$table.{$list[1]} LIKE :{$placeholder}";
                        }
                        elseif ($value === 'equals') {
                            $search_fields[] = "$table.{$list[1]} = :{$placeholder}";
                        }
                        elseif ($value === 'not_equals') {
                            $search_fields[] = "$table.{$list[1]} != :{$placeholder}";
                        }
                    }
                }
                $search_fields = implode(" OR ", $search_fields);
                if (!empty($search_fields)) {
                    $join_statement .= " AND ({$search_fields})";
                }


                $join_statement .= " GROUP BY node_data.nid";

                $limit = $definition['limit'] ?? 50;
                $offset = $definition['offset'] ?? 0;
                $page = (int) max(1, $request->get('page', 1));
                $offset = ($page - 1) * $limit;
                $limit_line = "LIMIT {$limit} OFFSET {$offset}";
                $join_statement .= " {$limit_line}";
                $this->search_query = $join_statement;
                return $join_statement;
            }

            elseif (isset($definition['type']) && $definition['type'] == 'user_type') {

                $select_part = array_map(function ($field) {
                    $list = explode(':', $field);
                    return  "{$list[0]}.{$list[1]} AS {$list[1]}";
                }, $definition['fields']);

                $tables = array_map(function ($field) {
                    $list = explode(':', $field);
                    return $list[0];
                },$definition['fields']);

                $tables = array_unique($tables);
                $tables = array_merge(['users'], $tables);
                $tables = array_unique($tables);
                // SELECT part of query
                $join_statement = "SELECT ".implode(", ", $select_part) . " FROM users INNER JOIN user_profile ON users.uid = user_profile.uid";

                $search_fields = [];

                foreach ($definition['filter_definitions'] as $key => $value) {

                    if (array_key_exists($key, $definition['exposed'] ?? []) && $definition['exposed'][$key] === true) {
                        $list = explode(':', $key);
                        $table = $list[0];
                        $placeholder = $list[1];
                        $this->placeholders[] = $key;

                        if ($value === 'contains' || $value === 'starts_with' || $value === 'ends_with') {
                            $search_fields[] = "$table.{$list[1]} LIKE :{$placeholder}";
                        }
                        elseif ($value === 'equals') {
                            $search_fields[] = "$table.{$list[1]} = :{$placeholder}";
                        }
                        elseif ($value === 'not_equals') {
                            $search_fields[] = "$table.{$list[1]} != :{$placeholder}";
                        }
                    }
                }
                $search_fields = implode(" OR ", $search_fields);
                if (!empty($search_fields)) {
                    $join_statement .= " WHERE {$search_fields}";
                }
                $join_statement .= " GROUP BY users.uid";

                $limit = $definition['limit'] ?? 50;
                $offset = $definition['offset'] ?? 0;
                $page = (int) max(1, $request->get('page', 1));
                $offset = ($page - 1) * $limit;
                $limit_line = "LIMIT {$limit} OFFSET {$offset}";
                $join_statement .= " {$limit_line}";
                $this->search_query = $join_statement;
                return $join_statement;
            }

        }
        return null;
    }

    public function runQuery(string $key, Request $request): void
    {
        $definition = $this->getSetting($key);
        $place_holders_values = [];
        foreach ($this->placeholders as $placeholder) {
            $list = explode(':', $placeholder);
            $name  = end($list);
            $place_holders_values[$placeholder] = $request->get($name);
        }
        $query = Database::database()->con()->prepare($this->search_query);
        foreach ($place_holders_values as $key => $value) {

            $list = explode(':', $key);
            $name  = end($list);
            $filter = $definition['filter_definitions'][$key] ?? "equals";
            if ($filter === 'contains') {
                $query->bindValue(":{$name}", "%{$value}%");
            }
            elseif ($filter === 'starts_with') {
                $query->bindValue(":{$name}", "%{$value}");
            }
            elseif ($filter === 'ends_with') {
                $query->bindValue(":{$name}", "{$value}%");
            }
            elseif ($filter === 'equals' || $filter === 'not_equals') {
                $query->bindParam(":{$name}", $value);
            }
        }
        $query->execute();
        $this->results = $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSearchQuery(): string
    {
        return $this->search_query;
    }

    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public static function searchManager(): self
    {
        return new self();
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public static function buildForm(string $search_key, $wrapper = false): ?string
    {
        $definition = SearchManager::searchManager()->getSetting($search_key);
        if (empty($definition['exposed'])) {
            return null;
        }

        $anonymous = new class () {use NodeFunction;
           public static function find(string $content_type, $field_name) {
               $content_type_d = ContentDefinitionManager::contentDefinitionManager()->getContentType($content_type);
               $fields = $content_type_d['fields'];
               return self::findField($fields, $field_name);
           }
        };

        $field_names = [];
        foreach ($definition['exposed'] as $key => $value) {

            if ($value === true) {
                $list = explode(':', $key);

                if ($definition['type'] === 'content_type' && $list[0] !== 'node_data') {
                    $field = $anonymous::find($list[0], $list[1]);
                    if ($field) {
                        $field_names[$list[1]] = [
                            'type' => $field['type'],
                            'name' => end($list),
                            'label' => $field['label'],
                        ];
                    }
                }
                else {
                    $field_names[$list[1]] = [
                        'type' => 'text',
                        'name' => end($list),
                        'label' => ucfirst(str_replace('_', ' ', $list[1])),
                    ];
                }
            }

        }
        return View::view('default.view.search_form_build', ['exposed_fields' => $field_names, 'search_key' => $search_key]);
    }
}