<?php

namespace Simp\Core\modules\mongodb;

use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Exception\Exception;
use RuntimeException;

/**
 * Represents a connection to a MongoDB database using the MongoDB PHP library.
 * Provides methods to establish a connection and retrieve the client or database instance.
 */

class ClientConnection
{
    /**
     * The client instance used for making requests.
     */
    protected Client $client;

    /**
     * Constructor method for initializing the database connection.
     *
     * @param string $host The hostname of the database server.
     * @param string $database The name of the database to connect to.
     * @param int $port The port number for the database connection. Defaults to 27017.
     * @param string|null $username The username for authentication. Defaults to null.
     * @param string|null $password The password for authentication. Defaults to null.
     * @param string $authSource The authentication source. Defaults to 'admin'.
     * @return void
     */
    public function __construct(
        protected string $host,
        protected string $database,
        protected int $port = 27017,
        protected ?string $username = null,
        protected ?string $password = null,
        protected string $authSource = 'admin'
    ) {
        $this->connect();
    }

    /**
     * Establishes a connection to the MongoDB server.
     *
     * Builds the connection URI based on the provided configuration values,
     * including optional authentication credentials. On successful connection,
     * initializes the MongoDB client. In case of an error, an exception is thrown.
     *
     * @return void
     * @throws RuntimeException If the connection to the MongoDB server fails.
     */
    protected function connect(): void
    {
        try {
            if ($this->username && $this->password) {
                $uri = "mongodb://{$this->username}:{$this->password}@{$this->host}:{$this->port}/?authSource={$this->authSource}";
            } else {
                $uri = "mongodb://{$this->host}:{$this->port}";
            }

            $this->client = new Client($uri);
        } catch (Exception $e) {
            throw new RuntimeException("MongoDB connection failed: " . $e->getMessage());
        }
    }

    /**
     * Retrieves the client instance.
     *
     * @return \Simp\Core\modules\mongodb\Client The client instance.
     */
    public function getClient(): \Simp\Core\modules\mongodb\Client
    {
        return new \Simp\Core\modules\mongodb\Client($this->client);
    }

    /**
     * Retrieves the MongoDB database instance using the configured client and database name.
     *
     * @return Database The MongoDB database instance.
     */
    public function getDatabase(): Database
    {
        return $this->client->selectDatabase($this->database);
    }

    /**
     * Establishes and returns a new client connection using the provided credentials.
     *
     * @return ClientConnection The initialized client connection instance.
     * @throws RuntimeException If MongoDB credentials are not found.
     */
    public static function clientConnection(): ClientConnection
    {
        $credentials = ClientCredentials::credentials()->getCredentials();
        if (empty($credentials)) {
            throw new RuntimeException("MongoDB credentials not found.");
        }
        return new self(
            $credentials['host'],
            $credentials['database'],
            $credentials['port'],
            $credentials['username'],
            $credentials['password'],
            $credentials['auth_source']
        );
    }
}
