<?php

namespace Simp\Core\modules\logger;

use PDO;
use PDOException;
use Simp\Core\modules\database\Database;

class ServerLogger
{
    protected array $logs = [];
    protected PDO $con;

    public function __construct(int $limit = 50, int $offset = 0)
    {
        $this->con = Database::database()->con();
        $query = "SELECT * FROM activity ORDER BY created DESC LIMIT $limit OFFSET $offset";
        $query = $this->con->prepare($query);
        $query->execute();
        $this->logs = array_map(function ($item) {
            $item['memory'] = $this->readableMemory($item['memory']);
            $item['start'] = $this->date($item['start']);
            $item['elapsed'] = $this->date($item['elapsed']);
            $item['end'] = $this->date($item['end']);
            return $item;

        },$query->fetchAll());
    }

    public function getFilterNumber(int $limit = 50): array
    {
        $filters = [
            'limit' => $limit,
            'offset_max' => 1,
        ];

        $query = "SELECT COUNT(*) AS total FROM activity LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->con->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $filters['offset_max'], PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $filters['count'] = $result['total'] ?? 0;
        } catch (PDOException $e) {
            // Log the error or handle it accordingly
            $filters['error'] = $e->getMessage();
        }

        return $filters;
    }


    protected function date(int $microtime): string
    {
        $timestamp = floor($microtime);
        $milliseconds = ($microtime - $timestamp) * 1000;
        return date("Y-m-d H:i:s", $timestamp) .' ' . sprintf("%03d", $milliseconds).'ms';
    }

    protected function readableMemory($size): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}