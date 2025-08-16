<?php


use Simp\Core\lib\installation\InstallerValidator;

@session_start();

require_once __DIR__ . "/../../vendor/autoload.php";

$validator = new InstallerValidator();
$validator->validate();


$fid = $_GET['fid'] ?? null;

$json = null;
if ($fid) {
    $file = \Simp\Core\modules\files\entity\File::load($fid);

    if ($file) {
        $result = $file->delete();

        if ($result) {
            sleep(3);
            $json = new \Symfony\Component\HttpFoundation\JsonResponse([
                'success' => true,
                'fid' => $fid,
            ],200);
        }
        else {
            $json = new \Symfony\Component\HttpFoundation\JsonResponse([
                'success' => false,
                'error' => "Unable to delete file",
            ],400);
        }

    }
    else {
        $json = new \Symfony\Component\HttpFoundation\JsonResponse([
            'success' => false,
            'error' => "File not found",
        ],400);
    }
}
else {
    $json = new \Symfony\Component\HttpFoundation\JsonResponse([
        'success' => false,
        'error' => "File not found",
    ],400);
}


$json->send();