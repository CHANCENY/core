<?php

namespace Simp\Core\modules\database;

use PDOStatement;
use PDO;
use Throwable;
use RuntimeException; // For throwing errors

class SPDOStatement extends PDOStatement
{
    // Store bound parameters for cache key generation
    private array $boundParams = [];
    // Store parameters passed directly to execute()
    private ?array $executeParams = null;

    private ?DatabaseCacheManager $cacheManager = null;
    private float $lastExecutionTime = 0.0;
    private bool $isExecuted = false; // Track if execute() was called

    // Cache settings for this statement (can be overridden)
    private bool $enableCache = false;
    private ?string $cacheTag = null;
    private bool $cacheHit = false;
    private mixed $cachedResult = null;

    // Constructor: Initialize cache manager and check global cache status
    protected function __construct()
    {
        try {
            $this->cacheManager = DatabaseCacheManager::manager();
            $this->enableCache = $this->cacheManager->isCacheActive();
            Database::staticLogger("SPDOStatement Info: Cache globally active: " . ($this->enableCache ? 'Yes' : 'No'));
        } catch (Throwable $e) {
            Database::staticLogger("SPDOStatement Error: Failed to initialize DatabaseCacheManager: " . $e->getMessage());
            $this->cacheManager = null;
            $this->enableCache = false;
        }
    }

    /**
     * Executes the prepared statement, handles caching logic, and invalidates cache on modification.
     */
    public function execute(?array $params = null): bool
    {
        $this->executeParams = $params;
        $this->isExecuted = false;
        $this->cacheHit = false;
        $this->cachedResult = null;
        $this->cacheTag = null;
        $isModifyingQuery = false;

        // 1. Determine Query Type (Modifying or Select)
        $trimmedQuery = ltrim($this->queryString);
        if (stripos($trimmedQuery, 'INSERT') === 0 ||
            stripos($trimmedQuery, 'UPDATE') === 0 ||
            stripos($trimmedQuery, 'DELETE') === 0 ||
            stripos($trimmedQuery, 'REPLACE') === 0 || // Consider other modifying types
            stripos($trimmedQuery, 'TRUNCATE') === 0 ||
            stripos($trimmedQuery, 'CREATE') === 0 ||
            stripos($trimmedQuery, 'ALTER') === 0 ||
            stripos($trimmedQuery, 'DROP') === 0) {
            $isModifyingQuery = true;
            Database::staticLogger("SPDOStatement Info: Detected modifying query type.");
        } else {
            Database::staticLogger("SPDOStatement Info: Detected SELECT (or non-modifying) query type.");
        }

        // 2. Generate Cache Tag (Only for potential SELECTs and if caching enabled)
        if (!$isModifyingQuery && $this->enableCache && $this->cacheManager) {
            try {
                $allParams = array_merge($this->boundParams, $this->executeParams ?? []);
                $this->cacheTag = $this->cacheManager->cacheTagCreate($this->queryString, $allParams);
                Database::staticLogger("SPDOStatement Info: Generated cache tag [{$this->cacheTag}] for query.");
            } catch (Throwable $e) {
                Database::staticLogger("SPDOStatement Cache Error (execute - tag generation): " . $e->getMessage() . " for query: " . $this->queryString);
                $this->enableCache = false; // Disable cache for this statement on error
            }
        }

        // 3. Execute the Query
        $start = microtime(true);
        try {
            $result = parent::execute($params);
            $this->isExecuted = $result;
        } catch (Throwable $e) {
            $this->isExecuted = false;
            $this->lastExecutionTime = microtime(true) - $start;
            Database::staticLogger("SPDOStatement Execute Error: " . $e->getMessage() . " Query: " . $this->queryString);
            throw $e; // Re-throw
        }
        $this->lastExecutionTime = microtime(true) - $start;

        // 4. Record Query Execution
        try {
            DatabaseRecorder::factory($this->queryString, $this->lastExecutionTime, $this->executeParams ?? $this->boundParams);
        } catch (Throwable $e) {
            Database::staticLogger("SPDOStatement Error: Failed to record query: " . $e->getMessage());
        }

        // 5. Invalidate Cache (If modifying query succeeded)
        if ($this->isExecuted && $isModifyingQuery && $this->enableCache && $this->cacheManager) {
            try {
                Database::staticLogger("SPDOStatement Cache Invalidation: Clearing ALL cache due to successful modifying query: " . $this->queryString);
                $cleared = $this->cacheManager->clearAllCache(); // Use clearAllCache as requested
                if (!$cleared) {
                    Database::staticLogger("SPDOStatement Cache Warning: clearAllCache() returned false.");
                }
            } catch (Throwable $e) {
                Database::staticLogger("SPDOStatement Cache Error (execute - invalidate): " . $e->getMessage() . " for query: " . $this->queryString);
            }
        }

        return $this->isExecuted;
    }

    // --- Fetch Methods with Caching ---

    /**
     * Checks the cache for the current query tag.
     * Sets $this->cachedResult and $this->cacheHit if found.
     */
    private function checkCache(): bool
    {
        $this->cacheHit = false;
        $this->cachedResult = null;

        // Check only if cache enabled, manager exists, and a tag was generated (i.e., likely a SELECT)
        if ($this->enableCache && $this->cacheManager && $this->cacheTag) {
            Database::staticLogger("SPDOStatement Cache Check: Checking cache for tag [{$this->cacheTag}].");
            try {
                // Attempt to get data from cache
                $cachedData = $this->cacheManager->getCache($this->cacheTag);

                // Check if the cache item exists and the retrieved data is not null
                // (Phpfastcache get() returns null on miss, but cache could store null)
                // Using isTagCached provides a more explicit check for existence.
                if ($this->cacheManager->isTagCached($this->cacheTag)) {
                    Database::staticLogger("SPDOStatement Cache Check: Cache HIT for tag [{$this->cacheTag}].");
                    $this->cachedResult = $cachedData;
                    $this->cacheHit = true;
                    return true;
                } else {
                    Database::staticLogger("SPDOStatement Cache Check: Cache MISS for tag [{$this->cacheTag}].");
                }
            } catch (Throwable $e) {
                Database::staticLogger("SPDOStatement Cache Error (checkCache - get): " . $e->getMessage() . " for tag: " . $this->cacheTag);
                $this->enableCache = false; // Disable cache on error for this statement
            }
        }
        return false;
    }

    /**
     * Saves data to the cache for the current query tag.
     */
    private function saveToCache(mixed $data): void
    {
        // Save only if cache enabled, manager exists, tag generated, and it wasn't a cache hit
        if ($this->enableCache && $this->cacheManager && $this->cacheTag && !$this->cacheHit) {
            Database::staticLogger("SPDOStatement Cache Save: Saving data to cache for tag [{$this->cacheTag}].");
            try {
                $saved = $this->cacheManager->resultCache($this->cacheTag, $data);
                if (!$saved) {
                    Database::staticLogger("SPDOStatement Cache Warning: resultCache() returned false for tag [{$this->cacheTag}].");
                }
            } catch (Throwable $e) {
                Database::staticLogger("SPDOStatement Cache Error (saveToCache - set): " . $e->getMessage() . " for tag: " . $this->cacheTag);
                $this->enableCache = false; // Disable cache on error for this statement
            }
        }
    }

    /**
     * Fetches all results, using cache if available.
     */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, ...$args): array
    {
        if (!$this->isExecuted) {
            throw new RuntimeException("Cannot fetchAll before executing the statement.");
        }

        // 1. Check Cache
        if ($this->checkCache()) {
            // Return a cached result (ensure it's an array)
            return is_array($this->cachedResult) ? $this->cachedResult : [];
        }

        // 2. Fetch from Database (Cache Miss)
        Database::staticLogger("SPDOStatement Info: Fetching all results from DB for query: " . $this->queryString);
        $result = parent::fetchAll($mode, ...$args);

        // 3. Save to Cache
        $this->saveToCache($result);

        return $result;
    }

    // --- Other Methods (Unchanged / No Caching) ---

    public function getLastExecutionTime(): float
    {
        return $this->lastExecutionTime;
    }

    public function bindColumn(int|string $column, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        return parent::bindColumn($column, $var, $type, $maxLength, $driverOptions);
    }

    public function bindParam(int|string $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        // Note: Parameters bound with bindParam are not automatically used in cache key generation
        // unless their values are also passed via execute($params).
        return parent::bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->boundParams[$param] = $value; // Store for cache key
        return parent::bindValue($param, $value, $type);
    }

    // fetch, fetchColumn, fetchObject remain without caching for simplicity/correctness
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (!$this->isExecuted) throw new RuntimeException("Cannot fetch before executing the statement.");
        return parent::fetch($mode, $cursorOrientation, $cursorOffset);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if (!$this->isExecuted) throw new RuntimeException("Cannot fetchColumn before executing the statement.");
        return parent::fetchColumn($column);
    }

    public function fetchObject(?string $class = "stdClass", array $constructorArgs = []): object|false
    {
        if (!$this->isExecuted) throw new RuntimeException("Cannot fetchObject before executing the statement.");
        return parent::fetchObject($class, $constructorArgs);
    }

    // Method to explicitly disable caching for this specific statement instance
    public function disableStatementCache(): void
    {
        Database::staticLogger("SPDOStatement Info: Caching explicitly disabled for this statement instance.");
        $this->enableCache = false;
    }
}

