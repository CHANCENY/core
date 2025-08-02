<?php

use Simp\Core\lib\app\App;
use Simp\Core\modules\cron\Cron;
use Simp\Core\modules\cron\CronHandler;

$root = getcwd();

$vendor = $root.'/vendor/autoload.php';

if (!file_exists($vendor)) {
    die("run this script on root directory of your project");
}

require_once $vendor;


try {
    App::consoleApp();

    $cron_manager = new Cron;
    $system = new \Simp\Core\lib\installation\SystemDirectory();

    $executor = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR. 'simp' . DIRECTORY_SEPARATOR . 'core' .
        DIRECTORY_SEPARATOR. 'src' . DIRECTORY_SEPARATOR . 'lib'
       . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'cron_executor.php';

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
