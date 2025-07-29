<?php

namespace Simp\Core\modules\logger;

use PDO;
use Simp\Core\modules\database\Database;

class DatabaseLogger
{
    protected array $logs = [];
    protected PDO $con;
    public function __construct(int $limit = 50, int $offset = 0)
    {
        $this->con = Database::database()->con();
        $query = "SELECT * FROM database_activity ORDER BY created DESC LIMIT $limit OFFSET $offset";
        $query = $this->con->prepare($query);
        $query->execute();
        $logs = $query->fetchAll();
        foreach ($logs as $log) {
            $log['executed_time'] = $this->humanReadableTime($log['executed_time']);
            $this->logs[$log['path']][] = $log;
        }
    }

    function humanReadableTime(float $seconds): string {
        if ($seconds >= 1) {
            return number_format($seconds, 2) . " seconds";
        } elseif ($seconds >= 0.001) {
            return number_format($seconds * 1000, 2) . " milliseconds";
        } else {
            return number_format($seconds * 1000000, 2) . " microseconds";
        }
    }

    public function logs(): array
    {
        return $this->logs;
    }
}