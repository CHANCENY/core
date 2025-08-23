<?php

$vendor = __DIR__. "/../../vendor/autoload.php";

use Simp\Core\lib\app\App;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverCheckException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheDriverNotFoundException;
use Phpfastcache\Exceptions\PhpfastcacheExtensionNotInstalledException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidConfigurationException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidTypeException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\cli\CliManager;

if (!file_exists($vendor)) {
    die("run this script on root directory of your project");
}

require_once $vendor;

/**
 * @return void
 * @throws PhpfastcacheCoreException
 * @throws PhpfastcacheDriverCheckException
 * @throws PhpfastcacheDriverException
 * @throws PhpfastcacheDriverNotFoundException
 * @throws PhpfastcacheExtensionNotInstalledException
 * @throws PhpfastcacheInvalidArgumentException
 * @throws PhpfastcacheInvalidConfigurationException
 * @throws PhpfastcacheInvalidTypeException
 * @throws PhpfastcacheLogicException
 */
function extracted($argv): void
{
    App::consoleApp();

    echo "Simp content management CLI" . PHP_EOL;
    echo implode("", array_fill(0, strlen("Simp content management CLI" . PHP_EOL), '_'));
    echo PHP_EOL . PHP_EOL;

// Grab the passed arguments
    $command_line = $argv[1] ?? null;
    $arguments = array_slice($argv, 2, count($argv) - 2);
    if (empty($command_line)) {
        die(PHP_EOL . 'command is not set' . PHP_EOL);
    }

    $cli_manager = new CliManager();

    $found_command = $cli_manager->$command_line();
    if (!$found_command) {
        echo PHP_EOL . "command not found" . PHP_EOL;
        exit(1);
    }

    $found_command($arguments);
}

try{

    extracted($argv);
}catch (Throwable $e){
    echo PHP_EOL . "sorry console not working properly" . PHP_EOL;
    \Simp\Core\modules\logger\ErrorLogger::logger()->logError($e);
}
