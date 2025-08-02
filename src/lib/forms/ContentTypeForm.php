<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Default\TextAreaField;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service;

class ContentTypeForm extends FormBase
{

    public function getFormId(): string
    {
        return 'content_type';
    }

    public function buildForm(array $form): array
    {
        $form['title'] = [
            'type' => 'text',
            'label' => 'Title',
            'name' => 'title',
            'id' => 'title',
            'class' => ['form-control'],
            'required' => true,
        ];
        $form['name'] = [
            'type' => 'text',
            'label' => 'Machine Name',
            'name' => 'name',
            'id' => 'name',
            'class' => ['form-control'],
            'required' => true,
        ];
        $form['description'] = [
            'type' => 'textarea',
            'label' => 'Description',
            'name' => 'description',
            'id' => 'description',
            'class' => ['form-control'],
            'handler' => TextAreaField::class
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        // TODO: Implement validateForm() method.
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function submitForm(array $form): void
    {
        $system = new SystemDirectory();
        $request = Service::serviceManager()->request;
        $data = $request->request->all();
        $line = str_replace(' ', '_', $data['name']);
        $line = 'content_'.strtolower($line);
        $data['machine_name'] = $line;
        $data['created'] = date('Y-m-d H:i A');
        $data['fields'] = [];
        $data['display_setting'] = [];
        $data['permission'] = [];

        $manager = ContentDefinitionManager::contentDefinitionManager();
        $exist_name = $manager->getContentType($line);
        if (empty($exist_name)) {
            $manager->addContentType($line, $data);
            Messager::toast()->addMessage("Content type structure created successfully");
            (new RedirectResponse('/admin/structure/types'))->send();
        }else {
            Messager::toast()->addMessage("Content type already exists");
        }

    }
}