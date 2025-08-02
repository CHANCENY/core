<?php

namespace Simp\Core\extends\auto_path\src\path;

use Exception;
use Simp\Core\modules\cron\event\CronExecutionResponse;
use Simp\Core\modules\cron\event\CronSubscriber;


class AutoPathCronSubscriber implements CronSubscriber
{

    /**
     * @throws Exception
     */
    public function run(string $name): CronExecutionResponse
    {
        // TODO: Implement run() method.
        $response = new CronExecutionResponse();
        $response->name = $name;
        $response->start_timestamp = time();;

        $result = AutoPathAlias::factory()->__populate();

        $created = count($result['created'] ?? []);
        $failed = count($result['failed'] ?? []);
        $response->message = "Aliases created ({$created}) and Failed ({$failed}).";
        $response->status = $created > 0 ? 200 : 400;
        $response->end_timestamp = time();
        $execution_time = $response->end_timestamp - $response->start_timestamp;
        $response->execution_time = $execution_time;
        return $response;
    }
}