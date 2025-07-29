<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\drag_and_drop_field\DragDropField;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\files\uploads\FormUpload;
use Simp\Core\modules\files\uploads\UrlUpload;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Default\FileField;
use Simp\Default\TextAreaField;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class FileAddForm extends FormBase
{

    protected bool $validated = true;
    public function getFormId(): string
    {
       return "add_file_form";
    }

    public function buildForm(array &$form): array
    {
        $form['files'] = [
            'type' => 'drag_and_drop',
            'label' => 'Upload Files',
            'name' => 'files',
            'id' => 'files',
            'class' => ['form-control', 'dropzone-hidden'],
            'options' => [
                'multiple' => 'multiple',
            ],
            'limit' => 10,
            'description' => 'Upload multiple files at once max 10 files.',
            'handler'=> DragDropField::class,
        ];
        $form['files_urls'] = [
            'type' => 'textarea',
            'label' => 'File URLs',
            'name' => 'files_urls',
            'id' => 'files_urls',
            'class' => ['form-control'],
            'options' => [
                'rows' => 5,
                'cols' => 5,
            ],
            'limit' => 1,
            'description' => 'Enter the URLs of the files you want to upload. One URL per line.',
            'handler' => TextAreaField::class,
        ];
        $form['submit'] = [
            'type' => 'submit',
            'default_value' => 'Submit',
            'name' => 'submit',
            'id' => 'submit',
            'class' => ['btn', 'btn-primary'],
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
       $urls = $form['files_urls']->getValue();
       if (empty($urls)) {
           $form['files']->setError('Please select a file or enter the URLs of the files you want to upload');
           $this->validated = false;
       }
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function submitForm(array &$form): void
    {
       if ($this->validated) {

           $urls = $form['files_urls']->getValue();

           $uploaded_files = [];

           if (!empty($urls)) {
               $urls = explode("\n", $urls);
               $urls = array_filter($urls);
               $urls = array_map('trim', $urls);

               foreach ($urls as $url) {

                   $url_uploader = new UrlUpload();
                   $url_uploader->addAllowedExtension("image/*");
                   $url_uploader->addAllowedExtension("application/*");
                   $url_uploader->addAllowedExtension("video/*");
                   $url_uploader->addAllowedExtension("audio/*");
                   $url_uploader->addAllowedExtension("text/*");
                   $url_uploader->addAllowedMaxSize(1024 * 1024 * 10);
                   $url_uploader->addUrl($url);
                   $url_uploader->validate();

                   // make sure the directory exists
                   if (!is_dir('public://files')) {
                       @mkdir('public://files', 0777, true);
                   }

                   if ($url_uploader->isValidated()) {
                       $file_name = $url_uploader->getParseFilename();
                       $url_uploader->moveFileUpload('public://files/'.$file_name);
                       $uploaded_files[] = $url_uploader->getFileObject();
                   }

               }

           }

           foreach ($uploaded_files as $file) {

               $file['uid'] = CurrentUser::currentUser()?->getUser()->getUid();
               $file['uri'] = $file['file_path'];
               File::create($file);


           }

           $redirect = new RedirectResponse('/admin/content');
           $redirect->send();
       }
    }
}
