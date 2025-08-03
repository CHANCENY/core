<?php

namespace Simp\Core\modules\connection\drivers;

use PDO;
use PDOException;
use RuntimeException;
use Simp\Core\modules\connection\interface\ConnectionInterface;
use Simp\Core\modules\database\Database;

/**
 * Class PDOConnection is a wrapper around the native PDO class for managing MySQL database operations.
 */

class PDOConnection implements ConnectionInterface
{
    protected PDO $pdo;

    public function __construct()
    {
        try {
            $this->pdo = Database::database()->con();
        } catch (PDOException $e) {
            throw new RuntimeException("MySQL Connection failed: " . $e->getMessage());
        }
    }

    /**
     * Executes an INSERT SQL statement with the specified parameters and returns the last inserted ID or a boolean.
     *
     * @param string $query The SQL query to be executed.
     * @param array $params Optional array of parameters to bind to the SQL query.
     * @return bool Returns the last inserted ID on success or false on failure.
     * @throws RuntimeException If the SQL execution fails, a runtime exception is thrown with an error message.
     */
    public function insert(string $query, array $params = []): bool
    {
        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new RuntimeException("Insert failed: " . $e->getMessage());
        }
    }

    /**
     * Executes an UPDATE SQL statement with the specified parameters and returns the number of affected rows.
     *
     * @param string $query The SQL query to be executed.
     * @param array $params Optional array of parameters to bind to the SQL query.
     * @return bool Returns the number of rows affected by the UPDATE statement.
     * @throws RuntimeException If the SQL execution fails, a runtime exception is thrown with an error message.
     */
    public function update(string $query, array $params = []): bool
    {
        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new RuntimeException("Update failed: " . $e->getMessage());
        }
    }

    /**
     * Executes a DELETE SQL statement with the specified parameters and returns the number of affected rows.
     *
     * @param string $query The SQL query to be executed.
     * @param array $params Optional array of parameters to bind to the SQL query.
     * @return bool Returns the number of rows affected by the DELETE statement.
     * @throws RuntimeException If the SQL execution fails, a runtime exception is thrown with an error message.
     */
    public function delete(string $query, array $params = []): bool
    {
        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new RuntimeException("Delete failed: " . $e->getMessage());
        }
    }

    /**
     * Executes a SELECT SQL statement with the specified parameters and returns the result set as an associative array.
     *
     * @param string $query The SQL query to be executed.
     * @param array $params Optional array of parameters to bind to the SQL query.
     * @return array Returns the result set as an associative array.
     * @throws RuntimeException If the SQL execution fails, a runtime exception is thrown with an error message.
     */
    public function select(string $query, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException("Select failed: " . $e->getMessage());
        }
    }

    /**
     * Executes a SQL statement and returns the execution status.
     *
     * @param string $query
     * @param array $params
     * @return bool Returns true if the execution was successful, or false otherwise.
     * @throws RuntimeException If the execution fails, a runtime exception is thrown with an error message.
     */
    public function execute(string $query, array $params = []): bool
    {
        try {
            return $this->pdo->exec($query) !== false;
        } catch (PDOException $e) {
            throw new RuntimeException("Execution failed: " . $e->getMessage());
        }
    }

    /**
     * Drops a table from the database if it exists.
     *
     * @param string $table The name of the table to be dropped.
     * @return bool Returns true if the operation was successful, otherwise false.
     */
    public function drop(string $table): bool
    {
        $sql = "DROP TABLE IF EXISTS `$table`";
        return $this->execute($sql);
    }

    /**
     * Retrieves the raw PDO instance.
     *
     * @return PDO Returns the underlying PDO instance for direct database interaction.
     */
    public function raw(): PDO
    {
        return $this->pdo;
    }

    public function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE :table");
        $stmt->execute(['table' => $table]);
        return $stmt->fetch() !== false;
    }

    public function createTable(string $query): bool
    {
        try {
            $this->pdo->exec($query);
            return true;
        } catch (\PDOException $e) {
            // Log or rethrow as needed
            return false;
        }
    }

    public function dropTable(string $table): bool
    {
        try {
            $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
            return true;
        } catch (\PDOException $e) {
            // Log or rethrow as needed
            return false;
        }
    }
}
