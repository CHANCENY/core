<?php

namespace Simp\Core\modules\structures\views;

use PDO;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\user\current_user\CurrentUser;
use Symfony\Component\HttpFoundation\Request;

class Display
{
    protected array $display = [];
    protected array $view = [];
    protected string $display_id = '';
    protected array $placeholders = [];
    protected string $view_display_query = '';
    protected array $view_display_results = [];
    protected array $raw_results = [];

    public function __construct(string $display_id)
    {
        $display = ViewsManager::viewsManager()->getDisplay($display_id);
        if (!empty($display)) {
            $this->display = $display;
            $this->display_id = $display_id;
            $this->view = ViewsManager::viewsManager()->getView($display['view']);
        }
    }

    public function isDisplayExists(): bool
    {
        return !empty($this->display);
    }

    public function isViewExists(): bool
    {
        return !empty($this->view);
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function isAccessible(): bool
    {
        $permissions = $this->display['permission'] ?? [];
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $current_user = CurrentUser::currentUser()?->getUser()?->getRoles() ?? [];
        if (!empty($current_user)) {
            $roles = array_map(function ($item) {
                return $item->getRoleName();
            }, $current_user);
            return !empty(array_intersect($roles, $permissions));
        }
        return in_array('anonymous', $permissions);
    }

    public function prepareQuery(Request $request): void
    {
        $generateMySQLQuery = function(Request $request, $fields,array $content_types, $sortCriteria = [], $filterCriteria = []): string
        {
            $selectFields = [];
            $fromTables = [];
            $default_filters['node_data.bundle'] = $content_types;

            foreach ($fields as $key => $details) {
               $field_name = $details['field'];
               $default_filters['node_data.bundle'][] = $details['content_type'] !== 'node' ? $details['content_type'] : null;
               $table =  $details['content_type'] !== 'node' ?"node__$field_name" : 'node_data';
               $fromTables[] = $table;
               $selectFields[] = $details['content_type'] !== 'node' ? "{$table}.{$field_name}__value AS {$field_name}" : "{$table}.{$field_name} AS {$field_name}";
            }
            $fromTables = array_unique($fromTables);
            if (($key = array_search('node_data', $fromTables)) !== false) {
                unset($fromTables[$key]);
                array_unshift($fromTables, 'node_data');
            }
            $default_filters['node_data.bundle'] = array_unique(array_filter($default_filters['node_data.bundle']));
            $selectFields = array_unique(array_filter($selectFields));
            if (array_search('node_data.nid', $selectFields) === false) {
                $new_select = $selectFields;
                $selectFields = ['node_data.nid AS nid', ...$new_select];

            }

            // Make basic select join query.
            $join_statement_line = "SELECT ".implode(', ', $selectFields)." FROM {$fromTables[0]}";
            for ($i = 1; $i < count($fromTables); $i++) {
                $join_statement_line .= " LEFT JOIN " . $fromTables[$i] . " ON " . $fromTables[$i] . ".nid = " . $fromTables[0] . ".nid";
            }

            // Add the filters for where clause. Default filters.
            $where_line = "WHERE ";
            foreach ($default_filters as $key => $value) {
                if (is_array($value)) {
                    $value = implode(',', array_map(function ($v) { return "'$v'"; },$value));
                    $where_line .= " {$key} IN ({$value})";
                }
                else {
                    $where_line .= " {$key} = '{$value}'";
                }
            }
            $join_statement_line .= " {$where_line}";

            // TODO: add custom filters
            $custom_filters = [];
            foreach ($filterCriteria as $details) {
                $field_name = $details['field'];
                $table = $details['content_type'] !== 'node' ?"node__$field_name" : 'node_data';
                $conjunction = $details['settings']['conjunction'] ?? 'AND';
                $param_name = $details['settings']['param_name'] ?? '';
                $field_name = $details['content_type'] !== 'node' ? "{$field_name}__value" : $field_name;
                $custom_filters[] = "{$table}.{$field_name} = :{$param_name}";
                $custom_filters[] = $conjunction;
                $param_value = null;
                if ($request->get($param_name))
                    $param_value =$request->get($param_name);
                elseif($request->request->get($param_name))
                    $param_value = $request->request->get($param_name);
                else
                    $param_value = json_decode($request->getContent(),true)[$param_name] ?? null;

                $this->placeholders[$param_name] = $param_value;
            }
            if (!empty($custom_filters)) {
                $custom_filters = array_slice($custom_filters, 0, count($custom_filters) - 1);
                $join_statement_line .= " AND (" . implode(', ', $custom_filters) . ")";
            }

            // add sort criteria
            if (!empty($sortCriteria)) {
                $sort_criteria = " ORDER BY ";
                foreach ($sortCriteria as $details) {
                    $field_name = $details['field'];
                    $table = $details['content_type'] !== 'node' ?"node__$field_name" : 'node_data';
                    $action = $details['settings']['order_in'] ?? 'ASC';
                    $sort_criteria .= "{$table}.{$field_name} {$action}";
                }
                $join_statement_line .= " {$sort_criteria}";
            }

            // Other settings
            if (!empty($this->display['settings']['limit'])) {
                $limit = (int) $this->display['settings']['limit'] ?? 20;
                $page = (int) max(1, $request->get('page', 1));
                $offset = ($page - 1) * $limit;
                $limit_line = "LIMIT {$limit} OFFSET {$offset}";
                $join_statement_line .= " {$limit_line}";
            }

            return $join_statement_line;

        };

        $content_types = [];
        if ($this->display['content_type'] === 'all') {
            $content_types = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
            $content_types = array_keys($content_types);
        }else {
            $content_types = [$this->display['content_type']];
        }
        $this->view_display_query = $generateMySQLQuery($request,
            $this->display['fields'],
            $content_types,
            $this->display['sort_criteria'],
            $this->display['filter_criteria']
        );
    }

    public function runDisplayQuery(): void
    {
        if (!empty($this->view_display_query)) {
            $statement = Database::database()->con()->prepare($this->view_display_query);
            if (!empty($this->placeholders)) {
                foreach ($this->placeholders as $key => $value) {
                    $statement->bindValue(':' . $key, $value);
                }
            }
            $statement->execute();
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            $processed = [];
            foreach ($result as $row) {
                $keys_list = array_keys($row);
                foreach ($keys_list as $key) {
                    if (isset($processed[$row['nid']][$key])) {
                        $new_value = $processed[$row['nid']][$key];
                        $init_value = is_numeric($row[$key]) ? (int)$row[$key] : $row[$key];
                        $processed[$row['nid']][$key] =  is_array($new_value)? [...$new_value,$init_value]: [$init_value];
                        $processed[$row['nid']][$key] = array_unique($processed[$row['nid']][$key]);
                    }else {
                        $processed[$row['nid']][$key] = is_numeric($row[$key]) ? (int)$row[$key] : $row[$key];
                    }
                }
            }

            $this->raw_results = array_values($processed);
            $this->view_display_results = array_map(function ($row) { return new ViewDataObject($row); }, array_values($processed));

        }

    }

    public static function display(string $display_id): Display
    {
        return new Display($display_id);
    }

    public function getDisplay(): array
    {
        return $this->display;
    }

    public function getView(): array
    {
        return $this->view;
    }

    public function getDisplayId(): string
    {
        return $this->display_id;
    }

    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    public function getDisplayParams(): array
    {
        return $this->display['params'] ?? [];
    }

    public function getViewDisplayQuery(): string
    {
        return $this->view_display_query;
    }

    public function getViewDisplayResults(): array
    {
        return $this->view_display_results;
    }

    public function getRawResults(): array
    {
        return $this->raw_results;
    }

    public function __toString(): string
    {
        return json_encode($this->raw_results);
    }

    public function pagination(Request $request): array
    {
        if (!empty($this->display['settings']['pagination'])) {

            $limit = (int) ($this->display['settings']['limit'] ?? 20);
            $page = (int) max(1, $request->get('page', 1));
            $offset = ($page - 1) * $limit;

            $pagination = function () {
                $content_types = $this->display['content_type'] === 'all' ?
                    array_keys(ContentDefinitionManager::contentDefinitionManager()->getContentTypes()) :
                    [$this->display['content_type']];

                $list = array_map(function ($content_type) {
                    return "'$content_type'";
                }, $content_types);

                return "SELECT COUNT(nid) FROM node_data WHERE bundle IN (" . implode(', ', $list) . ")";
            };

            $query = $pagination();
            $query = Database::database()->con()->prepare($query);
            $query->execute();
            $totalRows = $query->fetchColumn();
            if ($totalRows === 0 || $limit === 0) {
                return [
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total_rows' => $totalRows,
                        'total_pages' => 0,
                        'offset' => $offset,
                        'has_previous' => false,
                        'has_next' => false
                    ]
                ];
            }
            $totalPages = (int) ceil($totalRows / $limit);

            return [
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_rows' => $totalRows,
                    'total_pages' => $totalPages,
                    'offset' => $offset,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages
                ]
            ];
        }

        return [];
    }


    public function isPaginated(): bool
    {
        return !empty($this->display['settings']['pagination']) && $this->display['settings']['pagination'] === 'on';
    }


}