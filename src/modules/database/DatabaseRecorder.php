<?php

namespace Simp\Core\modules\database;

use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service; // Assuming this provides the Request object
use Throwable;

/**
 * Class DatabaseRecorder
 * Records database query activity on a per-request basis using static storage.
 */
class DatabaseRecorder
{
    // Static property to hold logs for the current request/process
    // Keyed by Request URI
    private static array $queryLog = [];
    // Flag to enable/disable recording globally
    private static bool $recordingEnabled = true;

    // Private constructor to prevent instantiation - all methods are static
    private function __construct() {}

    /**
     * Records a query execution event.
     *
     * @param string $query The SQL query string.
     * @param float $time The execution time in seconds.
     * @param array|null $params Parameters used with the query (optional).
     */
    public static function factory(string $query, float $time, ?array $params = null): void
    {
        if (!self::$recordingEnabled) {
            return; // Do nothing if recording is disabled
        }

        $uri = 'unknown_uri'; // Default URI if request object is unavailable
        try {
            // Dependency on Service Manager is kept for now.
            // Ideally, the Request object should be passed or available via a request context service.
            $request = Service::serviceManager()->request;
            if ($request instanceof Request) {
                $uri = $request->getRequestUri();
            }

            // Initialize log array for the URI if it doesn't exist
            if (!isset(self::$queryLog[$uri])) {
                self::$queryLog[$uri] = [];
            }

            // Add the log entry
            self::$queryLog[$uri][] = [
                'query' => $query,
                'params' => $params, // Store parameters
                'execute_time' => $time,
                'timestamp' => microtime(true) // Add timestamp for context
            ];

        } catch (Throwable $e) {
            // Log error if recording fails, using the static logger from the refined Database class
            Database::staticLogger("DatabaseRecorder Error: Failed to record query for URI [{$uri}]: " . $e->getMessage());
            // Avoid throwing exceptions from recorder to not break the main flow
        }
    }

    /**
     * Retrieves the recorded query activity for a specific URI or all URIs.
     *
     * @param string|null $uri The request URI to get logs for. If null, returns logs for all URIs recorded in this request.
     * @return array The query log data (empty array if no logs for the URI).
     */
    public static function getActivity(?string $uri = null): array
    {
        if ($uri === null) {
            return self::$queryLog; // Return all logs for the current request
        }
        return self::$queryLog[$uri] ?? []; // Return logs for specific URI or empty array
    }

    /**
     * Clears the query log for a specific URI or all logs for the current request.
     * This might be useful at the end of a request lifecycle or for testing.
     *
     * @param string|null $uri The URI to clear logs for. If null, clears all logs.
     */
    public static function clearActivity(?string $uri = null): void
    {
        if ($uri === null) {
            self::$queryLog = [];
        } elseif (isset(self::$queryLog[$uri])) {
            // Clear logs for a specific URI
            self::$queryLog[$uri] = [];
            // Alternatively, remove the URI key entirely:
            // unset(self::$queryLog[$uri]);
        }
    }

    /**
     * Enables query recording.
     */
    public static function enableRecording(): void
    {
        self::$recordingEnabled = true;
    }

    /**
     * Disables query recording.
     */
    public static function disableRecording(): void
    {
        self::$recordingEnabled = false;
    }

    /**
     * Checks if recording is currently enabled.
     * @return bool True if recording is enabled, false otherwise.
     */
    public static function isRecordingEnabled(): bool
    {
        return self::$recordingEnabled;
    }
}