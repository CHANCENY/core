<?php

namespace Simp\Core\modules\mongodb;

use MongoDB\Client as MongoClient;
use MongoDB\Database;
use MongoDB\Collection;

/**
 * Class Client provides an interface to interact with a MongoDB client.
 * It allows operations such as retrieving databases and collections,
 * creating collections, listing collections, and dropping collections.
 */

class Client
{
    protected MongoClient $client;

    public function __construct(MongoClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get a MongoDB\Database instance for the given database name.
     */
    public function getDatabase(string $databaseName): Database
    {
        return $this->client->selectDatabase($databaseName);
    }

    /**
     * Get a MongoDB\Collection instance for a given database and collection.
     */
    public function getCollection(string $databaseName, string $collectionName): Collection
    {
        return $this->getDatabase($databaseName)->selectCollection($collectionName);
    }

    /**
     * Create a new collection in the given database.
     */
    public function createCollection(string $databaseName, string $collectionName, array $options = []): void
    {
        $this->getDatabase($databaseName)->createCollection($collectionName, $options);
    }

    /**
     * List all collections in the given database.
     */
    public function listCollections(string $databaseName): array
    {
        $collections = $this->getDatabase($databaseName)->listCollections();
        return iterator_to_array($collections);
    }

    /**
     * Drop a collection from the given database.
     */
    public function dropCollection(string $databaseName, string $collectionName): void
    {
        $this->getDatabase($databaseName)->dropCollection($collectionName);
    }
}
