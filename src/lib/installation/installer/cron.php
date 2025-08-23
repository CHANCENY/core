<?php

$vendor = __DIR__. "/../../vendor/autoload.php";

use Simp\Core\lib\app\App;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\cron\Cron;
use Simp\Core\modules\cron\CronHandler;

if (!file_exists($vendor)) {
    die("run this script on root directory of your project");
}

require_once $vendor;


try {
    App::consoleApp();

    $cron_manager = new Cron;
    $system = new SystemDirectory();

    $executor = $system->webroot_dir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'cron_executor.php';

    $list = $cron_manager->getCrons();

    $queued = [];
    foreach ($list as $name=>$cron){
        $cron = $cron_manager->getCron($name);
        if ($cron instanceof CronHandler && $cron->isReadyNow()) {
            $queued[$name] = $cron;
        }
    }

    if (count($queued) > 0) {
        foreach ($queued as $k=>$cron) {
            $serialized = base64_encode(serialize($cron));
            $command = 'php '.$executor.' "' . $serialized . '" > /dev/null 2>&1 &';
            echo PHP_EOL. "$k has started execution".PHP_EOL;
            exec($command);
        }
    }

}catch (Throwable $e){
    echo PHP_EOL . "sorry console not working properly" . PHP_EOL .$e->getMessage();
}
