<?php

require_once __DIR__ . "/vendor/autoload.php";

\Simp\Core\lib\app\App::consoleApp();

$theme = \Simp\Core\modules\services\Service::get('database');
dump($theme);
