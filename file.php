<?php

require_once __DIR__ . "/vendor/autoload.php";

\Simp\Core\lib\app\App::consoleApp();


$file_manager = \Simp\Core\modules\files\entity\File::fileStorage();
$file_manager->addWhere("fid = :fid", [":fid" => 1]);
$file_manager->execute();

$usr_manager = \Simp\Core\modules\user\entity\User::userStorage();
$usr_manager->addWhere("uid = :uid", [":uid" => 1]);
$usr_manager->execute();

dump($usr_manager);