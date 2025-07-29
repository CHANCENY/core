<?php

namespace Simp\Core\modules\cron\event;

/**
 * Defines the contract for a subscriber that listens to and handles cron operations.
 */
interface CronSubscriber
{
    /**
     * Executes the cron process and returns the result of the execution.
     *
     * @return CronExecutionResponse The response object containing details about the cron execution.
     */
    public function run(string $name): CronExecutionResponse;

}