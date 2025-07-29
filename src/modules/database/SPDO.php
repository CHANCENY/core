<?php

namespace Simp\Core\modules\database;

use PDO;
use Simp\Core\lib\installation\SystemDirectory;
use Throwable; // Import Throwable for catching errors

class SPDO extends PDO
{
    // No need to store SystemDirectory if only used in log()

    // Match parent constructor signature more closely, added type hints assuming PHP 7.4+
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        // Call parent constructor
        parent::__construct($dsn, $username, $password, $options);
    }

    /**
     * Logs a message to the database log file.
     *
     * @param string $message The message to log.
     * @return bool True on success, false on failure.
     */
    public function log(string $message): bool
    {
        try {
            // Instantiate SystemDirectory here as it's only needed for logging
            $systemDirectory = new SystemDirectory();
            $logDirectory = $systemDirectory->setting_dir . DIRECTORY_SEPARATOR . 'database';
            $logFile = $logDirectory . DIRECTORY_SEPARATOR . 'database.log';

            // Ensure log directory exists
            if (!is_dir($logDirectory)) {
                // Attempt to create directory recursively with appropriate permissions
                if (!mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
                    // Log failure to create directory to PHP's error log
                    error_log("SPSPD::log failed: Could not create log directory: " . $logDirectory);
                    return false; // Indicate failure
                }
            }

            // Append message to log file with end-of-line character
            $result = file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND | LOCK_EX);

            if ($result === false) {
                // Log failure to write to file to PHP's error log
                error_log("SPDO::log failed: Could not write to log file: " . $logFile);
                return false; // Indicate failure
            }

            return true; // Indicate success

        } catch (Throwable $e) {
            // Log any exception during the logging process to PHP's error log
            error_log("SPDO::log exception: " . $e->getMessage());
            return false; // Indicate failure
        }
    }
}

