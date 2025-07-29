<?php

namespace Simp\Core\modules\cron\event;

class CronExecutionResponse
{
    public int $start_timestamp;
    public int $end_timestamp;
    public int $execution_time;

    public int $status;

    public string $name;

    public string $message;
}