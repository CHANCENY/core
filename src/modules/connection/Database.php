<?php

namespace Simp\Core\modules\connection;

use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\connection\drivers\MongoConnection;
use Simp\Core\modules\connection\drivers\PDOConnection;
use Simp\Core\modules\connection\interface\ConnectionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Handles database connections and provides a centralized access point for retrieving and managing the connection instance.
 */

class Database
{
    protected static ?ConnectionInterface $connection = null;

    /**
     * Establishes and returns a database connection instance based on the specified driver.
     *
     * If a connection has already been established, it returns the existing connection.
     * Otherwise, it initializes a new connection using the driver specified in the
     * environment variable 'DB_DRIVER', defaulting to 'mysql' if undefined.
     *
     * @return ConnectionInterface The established or newly created database connection instance.
     * @throws \Exception
     */
    public static function getConnection(): ConnectionInterface
    {
        if (self::$connection) {
            return self::$connection;
        }

        $system = new SystemDirectory();
        $connector = $system->setting_dir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.connector.yml';
        if (!file_exists($connector)) {
            throw new \Exception('Database connector file not found');
        }
        $driver = Yaml::parseFile($connector)['default'] ?? 'mysql';

        if ($driver === 'mongodb') {
            self::$connection = new MongoConnection();
        } else {
            self::$connection = new PDOConnection();
        }

        return self::$connection;
    }

    /**
     * Sets the connection instance to be used.
     *
     * @param ConnectionInterface $connection The connection instance to be set.
     * @return void
     */
    public static function setConnection(ConnectionInterface $connection): void
    {
        self::$connection = $connection;
    }

    /**
     * Resets the current connection instance to null.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$connection = null;
    }
}