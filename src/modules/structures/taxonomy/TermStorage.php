<?php

namespace Simp\Core\modules\structures\taxonomy;

use Simp\Core\modules\database\Database;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * Class TermStorageEntity
 *
 * Provides functionality to build SQL queries for handling Term storage.
 * This class allows dynamic query construction by adding joins, where clauses,
 * order by conditions, limits, and offsets.
 */
class TermStorage implements IteratorAggregate
{
    /**
     * @var Term[] Entities resulting from query execution.
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
    protected array $termStorageQuery = [
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
     */
    public function __construct()
    {
        $this->termStorageQuery['start'] = "SELECT term_data.* FROM term_data";

    }

    /**
     * Adds a join to the query.
     * @return $this
     */
    public function addJoin(string $table, string $alias, string $condition): self
    {
        $this->termStorageQuery['joins'][] = sprintf('JOIN %s %s ON %s', $table, $alias, $condition);
        return $this;
    }

    /**
     * Adds a where clause to the query.
     * @return $this
     */
    public function addWhere(string $condition, array $params = []): self
    {
        $this->termStorageQuery['where'][] = $condition;
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
        $this->termStorageQuery['order'] = sprintf('ORDER BY %s %s', $column, $direction);
        return $this;
    }

    /**
     * Adds a limit clause to the query.
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->termStorageQuery['limit'] = 'LIMIT ' . $limit;
        return $this;
    }

    /**
     * Adds an offset clause to the query.
     * @return $this
     */
    public function offset(int $offset): self
    {
        $this->termStorageQuery['offset'] = 'OFFSET ' . $offset;
        return $this;
    }

    /**
     * Executes the built query and hydrates Term entities.
     */
    public function execute(string $connector = 'AND'): self
    {
        $sql = [];
        $sql[] = $this->termStorageQuery['start'];

        if (!empty($this->termStorageQuery['joins'])) {
            $sql[] = implode(' ', $this->termStorageQuery['joins']);
        }

        if (!empty($this->termStorageQuery['where'])) {
            $sql[] = 'WHERE ' . implode(sprintf(' %s ', $connector), $this->termStorageQuery['where']);
        }

        if (!empty($this->termStorageQuery['group'] ?? '')) {
            $sql[] = $this->termStorageQuery['group'];
        }

        if (!empty($this->termStorageQuery['order'])) {
            $sql[] = $this->termStorageQuery['order'];
        }

        if (!empty($this->termStorageQuery['limit'])) {
            // ensure this is like "LIMIT 3", not a placeholder
            $sql[] = $this->termStorageQuery['limit'];
        }

        if (!empty($this->termStorageQuery['offset'])) {
            $sql[] = $this->termStorageQuery['offset'];
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
        $this->entities = $rows;
        return $this;
    }


    /**
     * Returns all entities.
     *
     * @return Term[]
     */
    public function all(): array
    {
        return $this->entities;
    }

    /**
     * Returns the first entity or null.
     */
    public function first(): ?Term
    {
        return $this->entities[0] ?? null;
    }

    /**
     * Returns the last entity or null.
     */
    public function last(): ?Term
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
            $sql[] = "SELECT COUNT(*) as cnt FROM term_data";

            if (!empty($this->termStorageQuery['joins'])) {
                $sql[] = implode(' ', $this->termStorageQuery['joins']);
            }

            if (!empty($this->termStorageQuery['where'])) {
                $sql[] = "WHERE " . implode(" AND ", $this->termStorageQuery['where']);
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
            // If Term is an object with properties
            if (is_object($entity) && isset($entity->$column)) {
                $values[] = $entity->$column;
            }
            // If Term is an array (fallback)
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
     * @return array An associative array with the column value as key and Term as value.
     */
    public function keyBy(string $column): array
    {
        $result = [];

        foreach ($this->entities as $entity) {
            // If Term is an object with properties
            if (is_object($entity) && isset($entity->$column)) {
                $result[$entity->$column] = $entity;
            }
            // If Term is an array (fallback)
            elseif (is_array($entity) && array_key_exists($column, $entity)) {
                $result[$entity[$column]] = $entity;
            }
        }

        return $result;
    }

    /**
     * Applies a callback to each loaded entity and returns the results.
     *
     * @param callable $callback A function that receives a Term and returns a transformed value.
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
     * Filter Terms that are published.
     */
    public function published(): self
    {
        return $this->addWhere('status = :status', ['status' => 1]);
    }

    /**
     * Filter Terms by author ID.
     *
     * @param int $uid The user ID of the author.
     */
    public function byAuthor(int $uid): self
    {
        return $this->addWhere('uid = :uid', ['uid' => $uid]);
    }

    /**
     * Filter Terms by a specific content type (bundle).
     */
    public function byBundle(string $bundle): self
    {
        return $this->addWhere('bundle = :bundleFilter', ['bundleFilter' => $bundle]);
    }

    /**
     * Filter Terms created after a certain timestamp.
     */
    public function createdAfter(int $timestamp): self
    {
        return $this->addWhere('created > :createdAfter', ['createdAfter' => $timestamp]);
    }

    /**
     * Filter Terms created before a certain timestamp.
     */
    public function createdBefore(int $timestamp): self
    {
        return $this->addWhere('created < :createdBefore', ['createdBefore' => $timestamp]);
    }

    /**
     * Filter Terms created within the last given number of days.
     *
     * @param int $days Number of days to look back. Default is 7.
     */
    public function recent(int $days = 7): self
    {
        $timestamp = strtotime(sprintf('-%d days', $days));
        return $this->createdAfter($timestamp);
    }

    /**
     * Filter Terms updated after a certain timestamp.
     *
     * @param int $timestamp UNIX timestamp to filter Terms updated after.
     */
    public function updatedAfter(int $timestamp): self
    {
        return $this->addWhere('updated > :updatedAfter', ['updatedAfter' => $timestamp]);
    }

    /**
     * Filter Terms updated before a certain timestamp.
     *
     * @param int $timestamp UNIX timestamp to filter Terms updated before.
     */
    public function updatedBefore(int $timestamp): self
    {
        return $this->addWhere('changed < :updatedBefore', ['updatedBefore' => $timestamp]);
    }

    /**
     * Filter Terms by a taxonomy term (category, tag, etc.).
     *
     * @param int $termId The taxonomy term ID to filter Terms by.
     * @param string $field The field in Term table that stores term reference. Defaults to 'tid'.
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

            /**@var Term $a **/
            /**@var Term $b **/

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

        // Build subquery to count the field per Term, with proper alias
        $subquery = sprintf('(SELECT nid, COUNT(%s) AS field_count FROM %s GROUP BY nid) AS %s', $field, $table, $alias);

        // Add LEFT JOIN with the subquery
        $this->termStorageQuery['joins'][] = sprintf('LEFT JOIN %s ON %s.nid = term_data.nid', $subquery, $alias);

        // Update SELECT to include all term_data fields plus the count from subquery
        $this->termStorageQuery['start'] = sprintf('SELECT term_data.*, %s.field_count FROM term_data', $alias);

        // Order by the counted field
        $this->termStorageQuery['order'] = sprintf('ORDER BY %s.field_count %s', $alias, $direction);

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
        $this->termStorageQuery['start'] = "SELECT COUNT(*) AS total FROM term_data";
        $limit = empty($this->termStorageQuery['limit']) ? 20 : (int) str_replace('LIMIT ', '', $this->termStorageQuery['limit']);
        $this->termStorageQuery['limit'] = "";
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
     * Randomizes the order of Terms in the query.
     */
    public function pickRandom(): static
    {
        $this->termStorageQuery['order'] = empty($this->termStorageQuery['order']) ?
            'ORDER BY RAND()' :
            $this->termStorageQuery['order'] . ', RAND()';
        return $this;
    }

}