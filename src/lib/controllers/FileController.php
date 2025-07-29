<?php

namespace Simp\Core\lib\controllers;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\forms\FileAddForm;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\files\helpers\FileFunction;
use Simp\Core\modules\files\uploads\FormUpload;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class FileController
{
    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function add_file(...$args):Response|RedirectResponse
    {
        extract($args);

        $formBase = new FormBuilder(new FileAddForm);
        $formBase->getFormBase()->setFormMethod('POST');
        $formBase->getFormBase()->setFormEnctype('multipart/form-data');
        $formBase->getFormBase()->setFormAction('/file/add');
        return new Response(View::view('default.view.file_form',['_form'=>$formBase]), Response::HTTP_OK);
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function file_upload(...$args):Response
    {
        extract($args);
        $files = $_FILES['files'] ?? [];
        if (empty($files)) {
            return new JsonResponse(['results'=> 'No files found', 'status' => 'error']);
        }

        $uploads = [];

        if (array_key_exists('name', $files) && is_array($files['name'])) {

            foreach ($files['name'] as $key => $file) {

                // file upload object.
                $file_object = [
                    'name' => $file,
                    'type' => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key],
                    'full_path' => $files['full_path'][$key],
                ];

                $form_uploader = new FormUpload();
                $form_uploader->addAllowedExtension("image/*");
                $form_uploader->addAllowedExtension("application/*");
                $form_uploader->addAllowedExtension("video/*");
                $form_uploader->addAllowedExtension("audio/*");
                $form_uploader->addAllowedExtension("text/*");

                // max allowed is 10 mb
                $form_uploader->addAllowedMaxSize(1024 * 1024 * 10);
                $form_uploader->addFileObject($file_object);
                $form_uploader->validate();

                // make sure the directory exists
                if (!is_dir('public://files')) {
                    @mkdir('public://files', 0777, true);
                }

                if ($form_uploader->isValidated()) {
                    $form_uploader->moveFileUpload('public://files/'.$form_uploader->getParseFilename());
                    $uploads[] = $form_uploader->getFileObject();
                }

            }
        }

        elseif (!empty($files['name']) && is_string($files['name'])) {

            // file upload object.
            $file_object = [
                'name' => $files['name'],
                'type' => $files['type'],
                'tmp_name' => $files['tmp_name'],
                'error' => $files['error'],
                'size' => $files['size'],
                'full_path' => $files['full_path'],
            ];

            $form_uploader = new FormUpload();
            $form_uploader->addAllowedExtension("image/*");
            $form_uploader->addAllowedExtension("application/*");
            $form_uploader->addAllowedExtension("video/*");
            $form_uploader->addAllowedExtension("audio/*");
            $form_uploader->addAllowedExtension("text/*");

            // max allowed is 10 mb
            $form_uploader->addAllowedMaxSize(1024 * 1024 * 10);
            $form_uploader->addFileObject($file_object);
            $form_uploader->validate();

            // make sure the directory exists
            if (!is_dir('public://files')) {
                @mkdir('public://files', 0777, true);
            }

            if ($form_uploader->isValidated()) {
                $form_uploader->moveFileUpload('public://files/'.$form_uploader->getParseFilename());
                $uploads[] = $form_uploader->getFileObject();
            }

        }

        foreach ($uploads as $key=>$upload) {
            $upload['uid'] = CurrentUser::currentUser()?->getUser()->getUid();
            $upload['uri'] = $upload['file_path'];
            $file = File::create($upload);
            if ($file) {
                $file = $file->toArray();
                $file['uri'] = FileFunction::reserve_uri($file['uri']);
                $uploads[$key] = $file;
            }

        }

        return new JsonResponse(['results'=> $uploads, 'status' => 'success']);
    }

    public function file_delete_ajx(...$args):Response
    {
        extract($args);
        $data = json_decode($request->getContent(), true);
        if (!empty($data['fid'])) {
            $file = File::load($data['fid']);
            if($file?->delete()) {
                return new JsonResponse(['results'=> 'File deleted successfully', 'status' => 'success']);
            }
        }
        return new JsonResponse(['results'=> [], 'status' => 'error']);
    }
}