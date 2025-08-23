<?php

$vendor = __DIR__. "/../../vendor/autoload.php";

use Simp\Core\modules\database\Database;
use Simp\Core\lib\app\App;
use Simp\Core\modules\cron\CronHandler;

if (!file_exists($vendor)) {
    die("run this script on root directory of your project");
}

require_once $vendor;

try{

    App::consoleApp();

    $encoded = $argv[1] ?? null;

    if (!$encoded) {
        echo "No data received.\n";
        exit(1);
    }

    /**@var CronHandler $cron_object**/
    $cron_object = unserialize(base64_decode($encoded));
    $response = $cron_object->run();

    $query = Database::database()->con()
        ->prepare("INSERT INTO simp_cron_logs (name, execute_time, start_time, end_time, status, message) VALUES (:name, :execute_time, :start_time, :end_time, :status, :message)");
    $query->execute([
        'name' => $response->name,
        'execute_time' => $response->execution_time,
        'start_time' => $response->start_timestamp,
        'end_time' => $response->end_timestamp,
        'status' => $response->status,
        'message' => $response->message,
    ]);

}catch (Throwable $e){

}
