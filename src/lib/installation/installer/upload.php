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

$settings = json_decode($_POST['field_settings'] ?? "", true);

$field_settings = empty($field_settings) ? $settings : $field_settings;

if ($field_settings) {
    $upload = new FormUpload();

    $upload->addAllowedMaxSize($field_settings['settings']['allowed_file_size'] ?? 6000000);
    array_map(function ($extension) use ($upload) {
        $upload->addAllowedExtension($extension);
    }, $field_settings['settings']['allowed_file_types'] ?? []);

    // limit number of files
    $limit = !empty($field_settings['limit']) ? (int)$field_settings['limit'] : 1;

    // Validation: enforce file limit
    if ($limit === 1 && is_array($_FILES[$field]['name'])) {
        $json = new JsonResponse([
            'success' => false,
            'error'   => "You are allowed to upload not more than {$limit} file.",
        ]);
        $json->setStatusCode(400)->send();
        exit;
    }

    if (is_array($_FILES[$field]['name']) && count($_FILES[$field]['name']) > $limit) {
        $json = new JsonResponse([
            'success' => false,
            'error'   => "Too many files attached to {$field_settings['label']} field. Only {$limit} files allowed.",
        ]);
        $json->setStatusCode(400)->send();
        exit;
    }

    // Normalize $_FILES into an array of file objects
    $files_object = [];
    if (is_array($_FILES[$field]['name'])) {
        // Multiple files
        for ($i = 0; $i < count($_FILES[$field]['name']); $i++) {
            $files_object[] = [
                'name'      => $_FILES[$field]['name'][$i],
                'type'      => $_FILES[$field]['type'][$i],
                'tmp_name'  => $_FILES[$field]['tmp_name'][$i],
                'size'      => $_FILES[$field]['size'][$i],
                'error'     => $_FILES[$field]['error'][$i],
                'full_path' => $_FILES[$field]['full_path'][$i] ?? $_FILES[$field]['name'][$i],
            ];
        }
    } else {
        // Single file
        $files_object[] = [
            'name'      => $_FILES[$field]['name'],
            'type'      => $_FILES[$field]['type'],
            'tmp_name'  => $_FILES[$field]['tmp_name'],
            'size'      => $_FILES[$field]['size'],
            'error'     => $_FILES[$field]['error'],
            'full_path' => $_FILES[$field]['full_path'] ?? $_FILES[$field]['name'],
        ];
    }

    // Ensure storage dir exists
    if (!is_dir("public://managed")) {
        mkdir("public://managed", 0777, true);
    }

    // Process uploads
    $uploaded = [];
    foreach ($files_object as $file) {
        $upload->addFileObject($file);
        $upload->validate();

        if ($upload->isValidated()) {
            $dest = "public://managed/" . $upload->getParseFilename();
            $upload->moveFileUpload($dest);

            sleep(2);

            $file_tmp = $upload->getFileObject();
            $file_tmp['uri'] = $file_tmp['file_path'];
            $file_tmp['uid'] = CurrentUser::currentUser()->getUser()->getUid();

            $file_entity = File::create($file_tmp)->toArray();
            $file_entity['uri'] = \Simp\Core\modules\files\helpers\FileFunction::reserve_uri($file_entity['uri'], true);

            $uploaded[] = $file_entity;
        }
    }

    (new JsonResponse($uploaded, 200))->send();
} else {
    (new JsonResponse([
        'success' => false,
        'error'   => "This field doesn't exist.",
    ], 200))->send();
}
