<?php

namespace Simp\Core\modules\connection\drivers;

use Exception;
use InvalidArgumentException;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\DeleteResult;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use Simp\Core\modules\connection\interface\ConnectionInterface;
use Simp\Core\modules\connection\translator\QueryTranslator;
use Simp\Core\modules\mongodb\Client;
use Simp\Core\modules\mongodb\ClientConnection;
use Simp\Core\modules\mongodb\ClientCredentials;

/**
 * Class responsible for managing MongoDB operations.
 */
class MongoConnection implements ConnectionInterface
{
    protected Client $client;
    protected Database $db;


    public function __construct()
    {
        $connect = ClientConnection::clientConnection();
        $this->client = $connect->getClient();
        $this->db = $this->client->getDatabase(ClientCredentials::credentials()->getCredentials()['database']);
    }

    /**
     * Executes a database operation based on the provided query.
     *
     *                     Supported types are: 'create', 'insert', 'select', 'update', and 'delete'.
     * @param array|string $query
     * @param array $params
     * @return mixed The result of the specified operation.
     * @throws Exception
     */
    public function execute(array|string $query, array $params = []): mixed
    {
        return match ($query['type']) {
            'create' => $this->createCollection($query),
            'insert' => $this->insert($query),
            'select' => $this->select($query),
            'update' => $this->update($query),
            'delete' => $this->delete($query),
            default => throw new InvalidArgumentException("Unsupported query type: {$query['type']}"),
        };
    }

    /**
     * Retrieves the specified database by name.
     *
     * @param string $name The name of the database to retrieve.
     * @return Database The database instance corresponding to the specified name.
     */
    protected function getDatabase(string $name): Database
    {
        return $this->client->getDatabase($name);
    }

    /**
     * Retrieves the specified collection from a database.
     *
     * @param string $dbName The name of the database containing the collection.
     * @param string $collectionName The name of the collection to retrieve.
     * @return Collection The collection instance corresponding to the specified database and collection name.
     */
    protected function getCollection(string $dbName, string $collectionName): Collection
    {
        return $this->getDatabase($dbName)->selectCollection($collectionName);
    }

    /**
     * Creates a collection within the specified database.
     *
     * @param array $query An associative array containing the database name and collection name. Must include 'database' for the database name and 'table' for the collection name.
     * @return bool Returns true upon successful creation of the collection.
     */
    protected function createCollection(array $query): bool
    {
        $db = $this->getDatabase($query['database']);
        $db->createCollection($query['table']);
        return true;
    }

    /**
     * Inserts a document into the specified collection.
     *
     *                     It should include keys 'database', 'table', and 'values'.
     * @param string $query
     * @param array $params
     * @return bool The result of the insert operation, typically including metadata about the operation performed.
     * @throws Exception
     */
    public function insert(string $query, array $params = []): bool
    {
        $query = (new QueryTranslator($query))->translate();

        $collection = $this->getCollection($query['database'], $query['collection']);
        $result = $collection->insertOne($query['document']);
        return $result instanceof InsertOneResult;
    }

    /**
     * Executes a query to select data from a specified collection.
     *
     *                     - 'database' (string): The name of the database.
     *                     - 'table' (string): The name of the table (collection) to query.
     *                     - 'where' (array, optional): The filter criteria for the query.
     *                     - 'columns' (array): The columns to select (use ['*'] to select all columns).
     * @param string $query
     * @param array $params
     * @return array A list of documents matching the query, represented as an array.
     * @throws Exception
     */
    public function select(string $query, array $params = []): array
    {
        $query = (new QueryTranslator($query))->translate();

        $collection = $this->getCollection($query['database'], $query['collection']);

        $filter = $query['filter'] ?? [];
        $projection = $query['projection'] === ['*'] ? [] : array_fill_keys($query['projection'], 1);

        foreach ($filter as $key => $value) {
            $filter[$key] = $params[$key] ?? '';
        }

        return $collection->find($filter)->toArray();
    }

    /**
     * Performs an update operation on the specified collection based on the provided query parameters.
     *
     *                     - 'database': The database name.
     *                     - 'table': The collection name.
     *                     - 'where': An optional filter array to identify the documents to update.
     *                     - 'values': The values to update in the specified documents.
     * @param string $query
     * @param array $params
     * @return bool The result of the update operation.
     * @throws Exception
     */
    public function update(string $query, array $params = []): bool
    {
        $query = (new QueryTranslator($query))->translate();
        $collection = $this->getCollection($query['database'], $query['collection']);
        $filter = $query['filter'] ?? [];
        foreach ($filter as $key => $value) {
            $filter[$key] = $params[$key] ?? '';
        }
        if (!isset($query['update'])) {
            return false;
        }
        return $collection->updateMany($filter, $query['update']) instanceof UpdateResult;
    }

    /**
     * Deletes documents from a specified collection based on a given query.
     *
     *                     Expected keys:
     *                     - 'database': The name of the database.
     *                     - 'table': The name of the collection (table).
     *                     - 'where' (optional): The filter criteria for selecting documents to delete.
     * @param string $query
     * @param array $params
     * @return bool The result of the delete operation, typically containing details about the operation.
     * @throws Exception
     */
    public function delete(string $query, array $params = []): bool
    {
        $query = (new QueryTranslator($query))->translate();
        $collection = $this->getCollection($query['database'], $query['collection']);
        $filter = $query['filter'] ?? [];

        foreach ($filter as $key => $value) {
            $filter[$key] = $params[$key] ?? '';
        }

        return $collection->deleteMany($filter) instanceof DeleteResult;
    }

    /**
     * Drops the specified table from the database.
     *
     * @param string $table The name of the table to be dropped.
     * @return bool True if the table was dropped successfully, false otherwise.
     */
    public function dropTable(string $table): bool
    {
        try {
            $this->db->dropCollection($table);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Checks if a table with the specified name exists in the database.
     *
     * @param string $table The name of the table to check for existence.
     * @return bool True if the table exists, false otherwise.
     */
    public function tableExists(string $table): bool
    {
        try {
            $collections = $this->db->listCollections();
            foreach ($collections as $collection) {
                if ($collection->getName() === $table) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Creates a new table based on the provided query.
     *
     * @param string $query The SQL query for creating the table.
     * @return bool True if the table is successfully created, false otherwise.
     */
    public function createTable(string $query): bool
    {
        try {
            $translator = new QueryTranslator();
            $parsed = $translator->parseCreateTable($query);
            $this->db->createCollection($parsed['collection']);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
