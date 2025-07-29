<?php

namespace Simp\Core\modules\cron;


use DateTime;
use DateInterval;
use Exception;
use PDO;
use Simp\Core\modules\cron\event\CronExecutionResponse;
use Simp\Core\modules\cron\event\CronSubscriber;
use Simp\Core\modules\database\Database;

class CronHandler
{
    public function __construct(
        protected string $name,
        protected string $title,
        protected string $description,
        protected string $timing,
        protected CronSubscriber $subscribers,
    ) {}

    /**
     * @throws Exception
     */
    public function isReadyNow(): bool
    {
        $timing = explode('|', $this->timing);
        return match ($timing[0]) {
            'every' => $this->validateEveryCron(),
            'once' => $this->validateOnceCron(),
            'ontime' => $this->validateRunOntimeCron(),
            default => false,
        };
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getTiming(): string
    {
        return $this->timing;
    }

    public function getSubscriber(): CronSubscriber
    {
        return $this->subscribers;
    }

    private function getRecordedCrons(): array|bool
    {
        $query = "SELECT * FROM cron_jobs WHERE name = :name LIMIT 1";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':name', $this->name);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC) ?? [];
    }

    private function validateEveryCron(): bool
    {
        $recorded_cron = $this->getRecordedCrons();
        $now = time();
        $timing = explode('|', $this->timing);
        $interval = $timing[1];
        $next_run = null;

        if ($interval === 'minute') {
            $next_run = $now + 60;
        }
        elseif ($interval === 'hour') {
            $next_run = $now + 3600;
        }
        elseif ($interval === 'day') {
            $next_run = $now + 86400;
        }
        elseif ($interval === 'week') {
            $next_run = $now + 604800;
        }
        elseif ($interval === 'month') {
            $next_run = $now + 2592000;
        }
        elseif ($interval === 'year') {
            $next_run = $now + 31536000;
        }

        if (empty($recorded_cron)) {
            $query = "INSERT INTO cron_jobs (name, last_run, next_run) VALUES (:name, :last_run, :next_run)";
            $query = Database::database()->con()->prepare($query);
            $query->bindParam(':name', $this->name);
            $query->bindParam(':last_run', $now);
            $query->bindParam(':next_run', $next_run);
            $query->execute();
            return true;
        }

        // now if not empty we need check if we can run this cron again now.
        if ($recorded_cron['next_run'] > $now) {
            return false;
        }
        $query = "UPDATE cron_jobs SET last_run = :last_run, next_run = :next_run WHERE name = :name";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':name', $this->name);
        $query->bindParam(':last_run', $now);
        $query->bindParam(':next_run', $next_run);
        $query->execute();
        return true;
    }

    /**
     *
     * @return bool
     */
    private function validateOnceCron(): bool
    {
        $recorded_cron = $this->getRecordedCrons();
        $timing = explode('|', $this->timing);
        $date = $timing[1];
        $next_run = strtotime($date);
        $now = time();
        if (empty($recorded_cron)) {
            $query = "INSERT INTO cron_jobs (name, last_run, next_run) VALUES (:name, :last_run, :next_run)";
            $query = Database::database()->con()->prepare($query);
            $query->bindParam(':name', $this->name);
            $query->bindParam(':last_run', $now);
            $query->bindParam(':next_run', $next_run);
            $query->execute();
            return $next_run > $now;
        }

        if ($recorded_cron['next_run'] > $now) {
            return false;
        }

        $query = "UPDATE cron_jobs SET last_run = :last_run, next_run = :next_run WHERE name = :name";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':name', $this->name);
        $query->bindParam(':last_run', $now);
        $query->bindParam(':next_run', $next_run);
        $query->execute();
        return $next_run > $now;
    }

    /**
     *
     * @throws Exception
     */
    private function validateRunOntimeCron(): bool
    {
        $recorded_cron = $this->getRecordedCrons();
        $timing = explode('|', $this->timing);
        $last_part_split = explode('@', $timing[1]);
        $date = strtotime($last_part_split[0]);
        $frequency = $last_part_split[1];
        $next_run = $date;
        $now = time();

        if (empty($recorded_cron)) {
            $query = "INSERT INTO cron_jobs (name, last_run, next_run) VALUES (:name, :last_run, :next_run)";
            $query = Database::database()->con()->prepare($query);
            $query->bindParam(':name', $this->name);
            $query->bindParam(':last_run', $now);
            $query->bindParam(':next_run', $next_run);
            $query->execute();
            return $next_run > $now;
        }

        $datetime = new DateTime();
        $datetime->modify("+". $frequency);
        $next_run = $datetime->getTimestamp();
        if ($recorded_cron['next_run'] > $now) {
            return false;
        }
        $query = "UPDATE cron_jobs SET last_run = :last_run, next_run = :next_run WHERE name = :name";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':name', $this->name);
        $query->bindParam(':last_run', $now);
        $query->bindParam(':next_run', $next_run);
        $query->execute();
        return $next_run > $now;
    }

    public function run(): CronExecutionResponse {
        return $this->subscribers->run($this->name);
    }

}