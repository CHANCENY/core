<?php

use Simp\Core\lib\installation\InstallerValidator;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\files\uploads\FormUpload;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\user\current_user\CurrentUser;
use Symfony\Component\HttpFoundation\JsonResponse;

@session_start();

require_once __DIR__ . "/../../vendor/autoload.php";

$validator = new InstallerValidator();
$validator->validate();


$field = key($_FILES);

$field_settings = ContentDefinitionManager::contentDefinitionManager()->getFieldByName($field);

if ($field_settings) {


    $upload = new FormUpload();

    $upload->addAllowedMaxSize($field_settings['settings']['allowed_file_size'] ?? 6000000);
    array_map(function ($extension) use ($upload) {
        $upload->addAllowedExtension($extension);
    }, $field_settings['settings']['allowed_file_types'] ?? []);

    // limit number of files
    $limit = 1;
    if (!empty($field_settings['limit'])) {
        $limit = (int)$field_settings['limit'];
    }

    if (count($_FILES[$field]['name']) > $limit) {
        $json = new JsonResponse([
            'success' => false,
            'error' => "Too much files attached to this {$field_settings['label']} field. only {$limit} files allowed."
        ]);
        $json->setStatusCode(400);
        $json->send();
        exit;
    }

   $files_object = [];

    for($i = 0; $i < count($_FILES[$field]['name']); $i++) {
        $files_object[] = array(
            'name' => $_FILES[$field]['name'][$i],
            'type' => $_FILES[$field]['type'][$i],
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'size' => $_FILES[$field]['size'][$i],
            'error' => $_FILES[$field]['error'][$i],
            'full_path' => $_FILES[$field]['full_path'][$i]
        );
    }

    if (!is_dir("public://managed")) {
        mkdir("public://managed", 0777, true);
    }

    $uploaded = [];
    foreach ($files_object as $file) {
        $upload->addFileObject($file);
        $upload->validate();
        sleep(2);
        if ($upload->isValidated()) {
            $dest = "public://managed/".$upload->getParseFilename();
            $upload->moveFileUpload($dest);
            $file_tmp = $upload->getFileObject();

            $file_tmp['uri'] = $file_tmp['file_path'];
            $file_tmp['uid'] = CurrentUser::currentUser()->getUser()->getUid();

            $file_entity = File::create($file_tmp);
            $file_entity = $file_entity->toArray();
            $file_entity['uri'] = \Simp\Core\modules\files\helpers\FileFunction::reserve_uri($file_entity['uri'],true);
            $uploaded[] = $file_entity;
        }
    }

    $json = new JsonResponse($uploaded, 200);
    $json->setStatusCode(200);
    $json->send();
}