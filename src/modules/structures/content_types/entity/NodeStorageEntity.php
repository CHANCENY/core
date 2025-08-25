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
        $this->nodeStorageQuery['where'][] = "bundle = :bundle";
        $this->parameters['bundle'] = $bundle;
    }

    /**
     * Adds a join to the query.
     * @param string $table
     * @param string $alias
     * @param string $condition
     * @return $this
     */
    public function addJoin(string $table, string $alias, string $condition): self
    {
        $this->nodeStorageQuery['joins'][] = "JOIN {$table} {$alias} ON {$condition}";
        return $this;
    }

    /**
     * Adds a where clause to the query.
     * @param string $condition
     * @param array $params
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
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->nodeStorageQuery['order'] = "ORDER BY {$column} {$direction}";
        return $this;
    }

    /**
     * Adds a limit clause to the query.
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->nodeStorageQuery['limit'] = "LIMIT {$limit}";
        return $this;
    }

    /**
     * Adds an offset clause to the query.
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): self
    {
        $this->nodeStorageQuery['offset'] = "OFFSET {$offset}";
        return $this;
    }

    /**
     * Executes the built query and hydrates Node entities.
     *
     * @return self
     */
    public function execute(): self
    {
        $sql = [];
        $sql[] = $this->nodeStorageQuery['start'];

        if (!empty($this->nodeStorageQuery['joins'])) {
            $sql[] = implode(' ', $this->nodeStorageQuery['joins']);
        }
        if (!empty($this->nodeStorageQuery['where'])) {
            $sql[] = 'WHERE ' . implode(' AND ', $this->nodeStorageQuery['where']);
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
            $placeholder = ':' . ltrim((string)$key, ':$'); // normalize (avoid `$field_count`/`field_count` issues)
            if (strpos($this->sql, $placeholder) === false) {
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
        $this->entities = array_map(fn(array $row) => new Node(...$row), $rows);

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
     *
     * @return Node|null
     */
    public function first(): ?Node
    {
        return $this->entities[0] ?? null;
    }

    /**
     * Returns the last entity or null.
     *
     * @return Node|null
     */
    public function last(): ?Node
    {
        return !empty($this->entities) ? end($this->entities) : null;
    }

    /**
     * Retrieve entities as an iterator (foreach support).
     *
     * @return Traversable
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
     *
     * @return array
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
     * @return int
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
     *
     * @return self
     */
    public function published(): self
    {
        return $this->addWhere('status = :status', ['status' => 1]);
    }

    /**
     * Filter nodes by author ID.
     *
     * @param int $uid The user ID of the author.
     * @return self
     */
    public function byAuthor(int $uid): self
    {
        return $this->addWhere('uid = :uid', ['uid' => $uid]);
    }

    /**
     * Filter nodes by a specific content type (bundle).
     *
     * @param string $bundle
     * @return self
     */
    public function byBundle(string $bundle): self
    {
        return $this->addWhere('bundle = :bundleFilter', ['bundleFilter' => $bundle]);
    }

    /**
     * Filter nodes created after a certain timestamp.
     *
     * @param int $timestamp
     * @return self
     */
    public function createdAfter(int $timestamp): self
    {
        return $this->addWhere('created > :createdAfter', ['createdAfter' => $timestamp]);
    }

    /**
     * Filter nodes created before a certain timestamp.
     *
     * @param int $timestamp
     * @return self
     */
    public function createdBefore(int $timestamp): self
    {
        return $this->addWhere('created < :createdBefore', ['createdBefore' => $timestamp]);
    }

    /**
     * Filter nodes created within the last given number of days.
     *
     * @param int $days Number of days to look back. Default is 7.
     * @return self
     */
    public function recent(int $days = 7): self
    {
        $timestamp = strtotime("-{$days} days");
        return $this->createdAfter($timestamp);
    }

    /**
     * Filter nodes updated after a certain timestamp.
     *
     * @param int $timestamp UNIX timestamp to filter nodes updated after.
     * @return self
     */
    public function updatedAfter(int $timestamp): self
    {
        return $this->addWhere('updated > :updatedAfter', ['updatedAfter' => $timestamp]);
    }

    /**
     * Filter nodes updated before a certain timestamp.
     *
     * @param int $timestamp UNIX timestamp to filter nodes updated before.
     * @return self
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
     * @return self
     */
    public function byTerm(int $termId, string $field = 'tid'): self
    {
        return $this->addWhere("{$field} = :termId", ['termId' => $termId]);
    }

    /**
     * Order loaded entities by the number of items in a given multi-value field.
     * This method works after execute() has been called.
     *
     * @param string $field The field/property to count items in (must be array or Countable).
     * @param string $direction 'ASC' or 'DESC'. Default 'DESC'.
     * @return self
     */
    public function orderByFieldCountPhp(string $field, string $direction = 'DESC'): self
    {
        usort($this->entities, function ($a, $b) use ($field, $direction) {

            /**@var Node $a **/
            /**@var Node $b **/

            $a_data = [];
            $b_data = [];

            if (empty($a->get($field)[0])) {
                $a_data = [];
            }
            else {
                $a_data = $a->get($field);
            }

            if (empty($b->get($field)[0])) {
                $b_data = [];
            }
            else {
                $b_data = $b->get($field);
            }

            $countA =  count($a_data);
            $countB =  count($b_data);

            if ($countA === $countB) return 0;
            return ($direction === 'ASC') ? ($countA <=> $countB) : ($countB <=> $countA);
        });

        return $this;
    }


}
