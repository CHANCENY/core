<?php

namespace Simp\Core\lib\forms;

use ZipArchive;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\modules\messager\Messager;
use Simp\Default\FileField;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ExtendAddFrom extends FormBase
{

    /**
     * @var false
     */
    private bool $validated = true;

    public function getFormId(): string
    {
        return 'extend_add_from';
    }

    public function buildForm(array &$form): array
    {
       $form['file_extension'] = [
           'type' => 'file',
           'label' => 'Extension',
           'name' => 'file_extension',
           'id' => 'file_extension',
           'class' => ['form-control'],
           'required' => true,
           'options' => [],
           'settings' => [
               'allowed_file_types'=> ['application/zip', 'application/x-zip','application/x-gzip'],
               'allowed_file_size' => 20000000
           ],
           'handler' => FileField::class,
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
        if ($form['file_extension']->getRequired() === 'required' && empty($form['file_extension']->getValue())) {
            $form['file_extension']->setError('Please select a file');
            $this->validated = false;
        }
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function submitForm(array &$form): void
    {
        if ($this->validated) {
            $file = $form['file_extension']->getValue();
            $file_name = $file['name'] ?? null;
            if (empty($file_name)) {
                Messager::toast()->addError('Please select a file');
                $redirect = new RedirectResponse('/admin/extend/add');
                $redirect->send();
                return;
            }
            $name = pathinfo($file_name, PATHINFO_FILENAME);
            if (!empty(ModuleHandler::factory()->getModule($name))) {
                Messager::toast()->addError('Module already exists');
                $redirect = new RedirectResponse('/admin/extend/add');
                $redirect->send();
                return;
            }

            $module_handler = ModuleHandler::factory();
            // unzip the file into modules directory
            $location = $module_handler->module_dir;
            $zip = new ZipArchive();
            $res = $zip->open($file['tmp_name']);
            if ($res === true) {
                $zip->extractTo($location );
                $zip->close();

                // validate the module.
                $file_info = $location . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $name .'.info.yml';
                if (!file_exists($file_info)) {

                    // delete the module
                    $all_files = glob($location . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . '*');
                    foreach ($all_files as $file) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                        else {
                            @rmdir($file);
                        }
                    }
                    @rmdir($location . DIRECTORY_SEPARATOR . $name);
                    Messager::toast()->addError('Module validation failed');
                    $redirect = new RedirectResponse('/admin/extend/add');
                    $redirect->send();
                    return;
                }
                Messager::toast()->addMessage('Module validated successfully');
                $module_handler = ModuleHandler::factory();
                $module_handler->installModule($name);
                $redirect = new RedirectResponse('/admin/extends');
                $redirect->send();
                return;
            }

        }
    }
}