<?php

namespace Simp\Core\modules\cron\default;

use Simp\Core\modules\cron\event\CronExecutionResponse;
use Simp\Core\modules\cron\event\CronSubscriber;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;

class DefaultCron implements CronSubscriber
{

    public function run(string $name): CronExecutionResponse
    {
        $start = time(); // seconds only
        try{
            $this->blockedUsers();
            $this->removeUnReferenceNode();
        }catch (\Throwable $exception){}
        $end = time(); // seconds only
        $execution_time = $end - $start;

        $response = new CronExecutionResponse();
        $response->message = 'Default cron executed successfully.';
        $response->status = 200;
        $response->execution_time = $execution_time;      // int
        $response->start_timestamp = $start;              // int
        $response->end_timestamp = $end;                  // int
        $response->name = $name;
        return $response;
    }

    private function blockedUsers(): void
    {
        $query = "DELETE FROM users WHERE status = 0";
        $database = Database::database();
        if ($database) {
            $query = $database->con()->prepare($query);
            $query->execute();
        }
    }

    private function removeUnReferenceNode(): void
    {
        $content_types = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
        $types = array_keys($content_types);
        if ($types) {
            $query = "DELETE FROM node_data WHERE bundle NOT IN ('" . implode("','", $types) . "')";
            $database = Database::database();
            if ($database) {
                $query = $database->con()->prepare($query);
                $query->execute();
            }
        }
    }


}