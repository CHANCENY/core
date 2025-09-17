<?php

namespace Simp\Core\modules\structures\content_types\entity;

use Simp\Core\modules\database\Database;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * Class NodeStorageEntity
 *
 * Provides functionality to build SQL queries for handling node storage.
 * This class allows dynamic query construction by adding joins, where clauses,
 * order by conditions, limits, and offsets.
 */
class NodeStorageEntity implements IteratorAggregate
{
    /**
     * @var Node[] Entities resulting from query execution.
     */
    protected array $entities = [];

    protected array $results = [];

    /**
     * @var string The final SQL query.
     */
    protected string $sql = '';

    /**
     * @var array Query parts.
     */
    protected array $nodeStorageQuery = [
        'start' => '',
        'joins' => [],
        'where' => [],
        'order' => '',
        'limit' => '',
        'offset' => '',
    ];

    /**
     * @var array Parameters to bind to PDO.
     */
    protected array $parameters = [];

    /**
     * Constructor method to initialize the object with the given bundle.
     *
     * @param string $bundle The bundle name used in the query initialization.
     */
    public function __construct(string $bundle)
    {
        $this->nodeStorageQuery['start'] = "SELECT node_data.* FROM node_data";
        if ($bundle !== '' && $bundle !== '0') {
            $this->nodeStorageQuery['where'][] = "bundle = :bundle";
            $this->parameters['bundle'] = $bundle;
        }

    }

    /**
     * Adds a join to the query.
     * @return $this
     */
    public function addJoin(string $table, string $alias, string $condition): self
    {
        $this->nodeStorageQuery['joins'][] = sprintf('JOIN %s %s ON %s', $table, $alias, $condition);
        return $this;
    }

    /**
     * Adds a where clause to the query.
     * @return $this
     */
    public function addWhere(string $condition, array $params = []): self
    {
        $this->nodeStorageQuery['where'][] = $condition;
        foreach ($params as $key => $value) {
            $this->parameters[$key] = $value;
        }

        return $this;
    }

    /**
     * Adds an order by clause to the query.
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->nodeStorageQuery['order'] = sprintf('ORDER BY %s %s', $column, $direction);
        return $this;
    }

    /**
     * Adds a limit clause to the query.
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->nodeStorageQuery['limit'] = 'LIMIT ' . $limit;
        return $this;
    }

    /**
     * Adds an offset clause to the query.
     * @return $this
     */
    public function offset(int $offset): self
    {
        $this->nodeStorageQuery['offset'] = 'OFFSET ' . $offset;
        return $this;
    }

    /**
     * Executes the built query and hydrates Node entities.
     */
    public function execute(string $connector = 'AND'): self
    {
        $sql = [];
        $sql[] = $this->nodeStorageQuery['start'];

        if (!empty($this->nodeStorageQuery['joins'])) {
            $sql[] = implode(' ', $this->nodeStorageQuery['joins']);
        }

        if (!empty($this->nodeStorageQuery['where'])) {
            $sql[] = 'WHERE ' . implode(sprintf(' %s ', $connector), $this->nodeStorageQuery['where']);
        }

        if (!empty($this->nodeStorageQuery['group'] ?? '')) {
            $sql[] = $this->nodeStorageQuery['group'];
        }

        if (!empty($this->nodeStorageQuery['order'])) {
            $sql[] = $this->nodeStorageQuery['order'];
        }

        if (!empty($this->nodeStorageQuery['limit'])) {
            // ensure this is like "LIMIT 3", not a placeholder
            $sql[] = $this->nodeStorageQuery['limit'];
        }

        if (!empty($this->nodeStorageQuery['offset'])) {
            $sql[] = $this->nodeStorageQuery['offset'];
        }

        $this->sql = implode(' ', $sql);

        $pdo = Database::database()->con();
        $stmt = $pdo->prepare($this->sql);

        // Bind ONLY placeholders that actually exist in the SQL.
        foreach ($this->parameters as $key => $value) {
            $placeholder = ':' . ltrim((string)$key, ':$');
            if (in_array(str_contains($this->sql, $placeholder), [0, false], true)) {
                continue; // skip params not present in SQL
            }

            $type = \PDO::PARAM_STR;
            if (is_int($value)) {
                $type = \PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = \PDO::PARAM_BOOL; $value = (int)$value;
            } elseif ($value === null) {
                $type = \PDO::PARAM_NULL;
            }

            $stmt->bindValue($placeholder, $value, $type);
        }

        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->results = $rows;

        $expected_columns = ['title','bundle','uid', 'nid', 'created', 'updated','lang', 'status'];
        $this->entities = array_filter(array_map(function ($row) use ($expected_columns): ?\Simp\Core\modules\structures\content_types\entity\Node {
            $keys = array_keys($row);
            $diff = array_diff($keys, $expected_columns);
            if ($diff !== []) {
                return null;
            }

            return new Node(...$row);
        },$rows));


        return $this;
    }


    /**
     * Returns all entities.
     *
     * @return Node[]
     */
    public function all(): array
    {
        return $this->entities;
    }

    /**
     * Returns the first entity or null.
     */
    public function first(): ?Node
    {
        return $this->entities[0] ?? null;
    }

    /**
     * Returns the last entity or null.
     */
    public function last(): ?Node
    {
        return $this->entities === [] ? null : end($this->entities);
    }

    /**
     * Retrieve entities as an iterator (foreach support).
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entities);
    }

    /**
     * Get raw SQL string.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Retrieves the parameters associated with the current instance.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Count entities.
     * If $fromDatabase = true, run a COUNT(*) query directly in DB.
     *
     * @param bool $fromDatabase Whether to count via SQL (true) or loaded entities (false).
     */
    public function count(bool $fromDatabase = false): int
    {
        if ($fromDatabase) {
            $sql = [];
            $sql[] = "SELECT COUNT(*) as cnt FROM node_data";

            if (!empty($this->nodeStorageQuery['joins'])) {
                $sql[] = implode(' ', $this->nodeStorageQuery['joins']);
            }

            if (!empty($this->nodeStorageQuery['where'])) {
                $sql[] = "WHERE " . implode(" AND ", $this->nodeStorageQuery['where']);
            }

            $query = implode(' ', $sql);
            $stmt = Database::database()->con()->prepare($query);
            foreach ($this->parameters as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            return (int) $stmt->fetchColumn();
        }

        return count($this->entities);
    }

    /**
     * Extracts a single column's values from loaded entities.
     *
     * @param string $column The column/property name to extract.
     * @return array An array of values for the given column.
     */
    public function pluck(string $column): array
    {
        $values = [];

        foreach ($this->entities as $entity) {
            // If Node is an object with properties
            if (is_object($entity) && isset($entity->$column)) {
                $values[] = $entity->$column;
            }
            // If Node is an array (fallback)
            elseif (is_array($entity) && array_key_exists($column, $entity)) {
                $values[] = $entity[$column];
            }
        }

        return $values;
    }

    /**
     * Re-index the loaded entities by a given column/property.
     *
     * @param string $column The column/property to use as the array key.
     * @return array An associative array with the column value as key and Node as value.
     */
    public function keyBy(string $column): array
    {
        $result = [];

        foreach ($this->entities as $entity) {
            // If Node is an object with properties
            if (is_object($entity) && isset($entity->$column)) {
                $result[$entity->$column] = $entity;
            }
            // If Node is an array (fallback)
            elseif (is_array($entity) && array_key_exists($column, $entity)) {
                $result[$entity[$column]] = $entity;
            }
        }

        return $result;
    }

    /**
     * Applies a callback to each loaded entity and returns the results.
     *
     * @param callable $callback A function that receives a Node and returns a transformed value.
     * @return array An array of transformed results.
     */
    public function map(callable $callback): array
    {
        $results = [];

        foreach ($this->entities as $entity) {
            $results[] = $callback($entity);
        }

        return $results;
    }

    /**
     * Filter nodes that are published.
     */
    public function published(): self
    {
        return $this->addWhere('status = :status', ['status' => 1]);
    }

    /**
     * Filter nodes by author ID.
     *
     * @param int $uid The user ID of the author.
     */
    public function byAuthor(int $uid): self
    {
        return $this->addWhere('uid = :uid', ['uid' => $uid]);
    }

    /**
     * Filter nodes by a specific content type (bundle).
     */
    public function byBundle(string $bundle): self
    {
        return $this->addWhere('bundle = :bundleFilter', ['bundleFilter' => $bundle]);
    }

    /**
     * Filter nodes created after a certain timestamp.
     */
    public function createdAfter(int $timestamp): self
    {
        return $this->addWhere('created > :createdAfter', ['createdAfter' => $timestamp]);
    }

    /**
     * Filter nodes created before a certain timestamp.
     */
    public function createdBefore(int $timestamp): self
    {
        return $this->addWhere('created < :createdBefore', ['createdBefore' => $timestamp]);
    }

    /**
     * Filter nodes created within the last given number of days.
     *
     * @param int $days Number of days to look back. Default is 7.
     */
    public function recent(int $days = 7): self
    {
        $timestamp = strtotime(sprintf('-%d days', $days));
        return $this->createdAfter($timestamp);
    }

    /**
     * Filter nodes updated after a certain timestamp.
     *
     * @param int $timestamp UNIX timestamp to filter nodes updated after.
     */
    public function updatedAfter(int $timestamp): self
    {
        return $this->addWhere('updated > :updatedAfter', ['updatedAfter' => $timestamp]);
    }

    /**
     * Filter nodes updated before a certain timestamp.
     *
     * @param int $timestamp UNIX timestamp to filter nodes updated before.
     */
    public function updatedBefore(int $timestamp): self
    {
        return $this->addWhere('changed < :updatedBefore', ['updatedBefore' => $timestamp]);
    }

    /**
     * Filter nodes by a taxonomy term (category, tag, etc.).
     *
     * @param int $termId The taxonomy term ID to filter nodes by.
     * @param string $field The field in node table that stores term reference. Defaults to 'tid'.
     */
    public function byTerm(int $termId, string $field = 'tid'): self
    {
        return $this->addWhere($field . ' = :termId', ['termId' => $termId]);
    }

    /**
     * Order loaded entities by the number of items in a given multi-value field.
     * This method works after execute() has been called.
     *
     * @param string $field The field/property to count items in (must be array or Countable).
     * @param string $direction 'ASC' or 'DESC'. Default 'DESC'.
     */
    public function orderByFieldCountPhp(string $field, string $direction = 'DESC'): self
    {
        usort($this->entities, function ($a, $b) use ($field, $direction): int {

            /**@var Node $a **/
            /**@var Node $b **/

            $a_data = [];
            $b_data = [];

            $a_data = empty($a->get($field)[0]) ? [] : $a->get($field);

            $b_data = empty($b->get($field)[0]) ? [] : $b->get($field);

            $countA =  count($a_data);
            $countB =  count($b_data);
            if ($countA === $countB) {
                return 0;
            }

            return ($direction === 'ASC') ? ($countA <=> $countB) : ($countB <=> $countA);
        });

        return $this;
    }

    public function orderByFieldCount(string $table, string $field, string $direction = 'DESC'): static
    {
        // Alias for the subquery
        $alias = $table . '_cnt';

        // Build subquery to count the field per node, with proper alias
        $subquery = sprintf('(SELECT nid, COUNT(%s) AS field_count FROM %s GROUP BY nid) AS %s', $field, $table, $alias);

        // Add LEFT JOIN with the subquery
        $this->nodeStorageQuery['joins'][] = sprintf('LEFT JOIN %s ON %s.nid = node_data.nid', $subquery, $alias);

        // Update SELECT to include all node_data fields plus the count from subquery
        $this->nodeStorageQuery['start'] = sprintf('SELECT node_data.*, %s.field_count FROM node_data', $alias);

        // Order by the counted field
        $this->nodeStorageQuery['order'] = sprintf('ORDER BY %s.field_count %s', $alias, $direction);

        return $this;
    }

    /**
     * Apply pagination to the query.
     *
     * @param int $page   Current page number (1-based).
     */
    public function paginate(int $page): array
    {
        // Step 1: Get total rows
        $this->nodeStorageQuery['start'] = "SELECT COUNT(*) AS total FROM node_data";
        $limit = empty($this->nodeStorageQuery['limit']) ? 20 : (int) str_replace('LIMIT ', '', $this->nodeStorageQuery['limit']);
        $this->nodeStorageQuery['limit'] = "";
        $this->execute();

        $total = $this->results[0]['total'] ?? 0;

        // Step 2: Calculate total pages
        $totalPages = (int) ceil($total / $limit);

        $this->execute();

        // Step 5: Return data with pagination info
        return [
            'current_page' => $page,
            'per_page' => $limit,
            'total_pages' => $totalPages,
            'total_items' => $total,
        ];
    }

    /**
     * Randomizes the order of nodes in the query.
     */
    public function pickRandom(): static
    {
        $this->nodeStorageQuery['order'] = empty($this->nodeStorageQuery['order']) ?
            'ORDER BY RAND()' :
            $this->nodeStorageQuery['order'] . ', RAND()';
        return $this;
    }

}