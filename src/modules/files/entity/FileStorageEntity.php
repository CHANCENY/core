<?php

namespace Simp\Core\modules\files\entity;

use Simp\Core\modules\database\Database;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * Class FileStorageEntity
 *
 * Provides functionality to build SQL queries for handling File storage.
 * This class allows dynamic query construction by adding joins, where clauses,
 * order by conditions, limit, and offsets.
 */
class FileStorageEntity implements IteratorAggregate
{
    /**
     * @var File[] Entities resulting from query execution.
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
    protected array $fileStorageQuery = [
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
     */
    public function __construct()
    {
        $this->fileStorageQuery['start'] = "SELECT file_managed.* FROM file_managed";
    }

    /**
     * Adds a join to the query.
     * @return $this
     */
    public function addJoin(string $table, string $alias, string $condition): self
    {
        $this->fileStorageQuery['joins'][] = sprintf('JOIN %s %s ON %s', $table, $alias, $condition);
        return $this;
    }

    /**
     * Adds a where clause to the query.
     * @return $this
     */
    public function addWhere(string $condition, array $params = []): self
    {
        $this->fileStorageQuery['where'][] = $condition;
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
        $this->fileStorageQuery['order'] = sprintf('ORDER BY %s %s', $column, $direction);
        return $this;
    }

    /**
     * Adds a limit clause to the query.
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->fileStorageQuery['limit'] = 'LIMIT ' . $limit;
        return $this;
    }

    /**
     * Adds an offset clause to the query.
     * @return $this
     */
    public function offset(int $offset): self
    {
        $this->fileStorageQuery['offset'] = 'OFFSET ' . $offset;
        return $this;
    }

    /**
     * Executes the built query and hydrates File entities.
     */
    public function execute(string $connector = 'AND'): self
    {
        $sql = [];
        $sql[] = $this->fileStorageQuery['start'];

        if (!empty($this->fileStorageQuery['joins'])) {
            $sql[] = implode(' ', $this->fileStorageQuery['joins']);
        }

        if (!empty($this->fileStorageQuery['where'])) {
            $sql[] = 'WHERE ' . implode(sprintf(' %s ', $connector), $this->fileStorageQuery['where']);
        }

        if (!empty($this->fileStorageQuery['group'] ?? '')) {
            $sql[] = $this->fileStorageQuery['group'];
        }

        if (!empty($this->fileStorageQuery['order'])) {
            $sql[] = $this->fileStorageQuery['order'];
        }

        if (!empty($this->fileStorageQuery['limit'])) {
            // ensure this is like "LIMIT 3", not a placeholder
            $sql[] = $this->fileStorageQuery['limit'];
        }

        if (!empty($this->fileStorageQuery['offset'])) {
            $sql[] = $this->fileStorageQuery['offset'];
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

        $expected_columns = ['fid','name','uid', 'uri', 'created','size', 'mime_type','extension'];
        $this->entities = array_filter(array_map(function ($row) use ($expected_columns): ?\Simp\Core\modules\files\entity\File {
            $keys = array_keys($row);
            $diff = array_diff($keys, $expected_columns);
            if ($diff !== []) {
                return null;
            }

            return new File(...$row);
        },$rows));


        return $this;
    }

    /**
     * Returns all entities.
     *
     * @return File[]
     */
    public function all(): array
    {
        return $this->entities;
    }

    /**
     * Returns the first entity or null.
     */
    public function first(): ?File
    {
        return $this->entities[0] ?? null;
    }

    /**
     * Returns the last entity or null.
     */
    public function last(): ?File
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
            $sql[] = "SELECT COUNT(*) as cnt FROM file_managed";

            if (!empty($this->fileStorageQuery['joins'])) {
                $sql[] = implode(' ', $this->fileStorageQuery['joins']);
            }

            if (!empty($this->fileStorageQuery['where'])) {
                $sql[] = "WHERE " . implode(" AND ", $this->fileStorageQuery['where']);
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
            // If File is an object with properties
            if (is_object($entity) && isset($entity->$column)) {
                $values[] = $entity->$column;
            }
            // If File is an array (fallback)
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
     * @return array An associative array with the column value as key and File as value.
     */
    public function keyBy(string $column): array
    {
        $result = [];

        foreach ($this->entities as $entity) {
            // If File is an object with properties
            if (is_object($entity) && isset($entity->$column)) {
                $result[$entity->$column] = $entity;
            }
            // If File is an array (fallback)
            elseif (is_array($entity) && array_key_exists($column, $entity)) {
                $result[$entity[$column]] = $entity;
            }
        }

        return $result;
    }

    /**
     * Applies a callback to each loaded entity and returns the results.
     *
     * @param callable $callback A function that receives a File and returns a transformed value.
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
     * Filter Files that are published.
     */
    public function published(): self
    {
        return $this->addWhere('status = :status', ['status' => 1]);
    }

    /**
     * Filter Files by author ID.
     *
     * @param int $uid The user ID of the author.
     */
    public function byAuthor(int $uid): self
    {
        return $this->addWhere('uid = :uid', ['uid' => $uid]);
    }

    /**
     * Filter Files by a specific content type (bundle).
     */
    public function byBundle(string $bundle): self
    {
        return $this->addWhere('bundle = :bundleFilter', ['bundleFilter' => $bundle]);
    }

    /**
     * Filter Files created after a certain timestamp.
     */
    public function createdAfter(int $timestamp): self
    {
        return $this->addWhere('created > :createdAfter', ['createdAfter' => $timestamp]);
    }

    /**
     * Filter Files created before a certain timestamp.
     */
    public function createdBefore(int $timestamp): self
    {
        return $this->addWhere('created < :createdBefore', ['createdBefore' => $timestamp]);
    }

    /**
     * Filter Files created within the last given number of days.
     *
     * @param int $days Number of days to look back. Default is 7.
     */
    public function recent(int $days = 7): self
    {
        $timestamp = strtotime(sprintf('-%d days', $days));
        return $this->createdAfter($timestamp);
    }

    /**
     * Filter Files updated after a certain timestamp.
     *
     * @param int $timestamp UNIX timestamp to filter Files updated after.
     */
    public function updatedAfter(int $timestamp): self
    {
        return $this->addWhere('updated > :updatedAfter', ['updatedAfter' => $timestamp]);
    }

    /**
     * Filter Files updated before a certain timestamp.
     *
     * @param int $timestamp UNIX timestamp to filter Files updated before.
     */
    public function updatedBefore(int $timestamp): self
    {
        return $this->addWhere('changed < :updatedBefore', ['updatedBefore' => $timestamp]);
    }

    /**
     * Filter Files by a taxonomy term (category, tag, etc.).
     *
     * @param int $termId The taxonomy term ID to filter Files by.
     * @param string $field The field in File table that stores term reference. Defaults to 'tid'.
     */
    public function byTerm(int $termId, string $field = 'tid'): self
    {
        return $this;
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

            /**@var File $a **/
            /**@var File $b **/

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

        // Build subquery to count the field per File, with proper alias
        $subquery = sprintf('(SELECT nid, COUNT(%s) AS field_count FROM %s GROUP BY nid) AS %s', $field, $table, $alias);

        // Add LEFT JOIN with the subquery
        $this->fileStorageQuery['joins'][] = sprintf('LEFT JOIN %s ON %s.nid = file_managed.nid', $subquery, $alias);

        // Update SELECT to include all file_managed fields plus the count from subquery
        $this->fileStorageQuery['start'] = sprintf('SELECT file_managed.*, %s.field_count FROM file_managed', $alias);

        // Order by the counted field
        $this->fileStorageQuery['order'] = sprintf('ORDER BY %s.field_count %s', $alias, $direction);

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
        $this->fileStorageQuery['start'] = "SELECT COUNT(*) AS total FROM file_managed";
        $limit = empty($this->fileStorageQuery['limit']) ? 20 : (int) str_replace('LIMIT ', '', $this->fileStorageQuery['limit']);
        $this->fileStorageQuery['limit'] = "";
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
     * Randomizes the order of Files in the query.
     */
    public function pickRandom(): static
    {
        $this->fileStorageQuery['order'] = empty($this->fileStorageQuery['order']) ?
            'ORDER BY RAND()' :
            $this->fileStorageQuery['order'] . ', RAND()';
        return $this;
    }

}