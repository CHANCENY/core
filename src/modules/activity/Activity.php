<?php

namespace Simp\Core\modules\activity;

use Throwable;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\event_subscriber\EventSubscriber;
use Simp\Core\modules\user\current_user\CurrentUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Activity implements EventSubscriber
{
    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function listeners(Request $request, Route $route, ?Response $response): void
    {
        // Log some executions
        $start_time = $GLOBALS['request_start_time'];
        $end_time = microtime(true);
        $time_elapsed = $end_time - $start_time;
        $memory_elapsed = memory_get_usage();
        $cpu_usage = getrusage();
        $user_cpu = $cpu_usage["ru_utime.tv_sec"] + $cpu_usage["ru_utime.tv_usec"] / 1e6;
        $system_cpu = $cpu_usage["ru_stime.tv_sec"] + $cpu_usage["ru_stime.tv_usec"] / 1e6;

        $data = [
            'start' => $start_time,
            'end' => $end_time,
            'elapsed' => $time_elapsed,
            'memory' => $memory_elapsed,
            'system_usage' => $system_cpu,
            'user_usage' => $user_cpu,
            'user' => CurrentUser::currentUser()?->getUser()->getUid() ?? 0,
            'path' => $request->getRequestUri(),
        ];

       try{
           $connection = Database::database()?->con();
           if ($connection) {
               $statement = $connection->prepare("INSERT INTO activity (start,end,elapsed,memory,system_usage,user_usage,user,path) VALUES (:start,:end,:elapsed,:memory,:system_usage,:user_usage,:user,:path)");
               $statement->execute($data);
           }
       }catch (Throwable){}
    }
}