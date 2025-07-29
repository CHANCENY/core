<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\views\ViewsManager;
use Simp\Default\FieldSetField;
use Simp\Default\SelectField;
use Simp\Fields\FieldBase;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service;

class ViewAddForm extends FormBase
{

    protected bool $validated = true;

    public function getFormId(): string
    {
       return 'view_add';
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function buildForm(array &$form): array
    {
        $routes = Caching::init()->get('system.routes.keys');
        $routes = array_values($routes);
        $routes = array_combine($routes, $routes);

        $content_list = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
        $content_list = array_keys($content_list);
        $content_list = array_combine($content_list, $content_list);

        $request = Service::serviceManager()->request;
        $view = ViewsManager::viewsManager()->getView($request->get('view_name', ''));

        $form['wrapper'] = [
            'type' => 'fieldset',
            'handler' => FieldSetField::class,
            'name' => 'wrapper',
            'class' => ['form-group'],
            'id' => 'wrapper',
            'label' =>'View basic information',
            'inner_field' => [
                'name' => [
                    'type' => 'text',
                    'label' => 'View Name',
                    'required' => true,
                    'id' => 'name',
                    'class' => ['form-control'],
                    'name' => 'name',
                    'default_value' => $view['name'] ?? null,
                ],
                'description' => [
                    'type' => 'text',
                    'label' => 'Description',
                    'required' => true,
                    'id' => 'description',
                    'class' => ['form-control'],
                    'name' => 'description',
                    'default_value' => $view['description'] ?? null,
                ],
            ]
        ];

        $form['page_settings'] = [
            'type' => 'fieldset',
            'handler' => FieldSetField::class,
            'name' => 'view_settings',
            'class' => ['form-group'],
            'id' => 'view_settings',
            'label' =>'Page settings',
            'inner_field' => [
                'content_type' => [
                    'type' => 'select',
                    'label' => 'Content Type',
                    'required' => true,
                    'id' => 'content_type',
                    'class' => ['form-control'],
                    'name' => 'content_type',
                    'option_values' => [
                        'all' => 'All',
                        ...$content_list,
                    ],
                    'handler' => SelectField::class,
                    'default_value' => $view['content_type'] ?? 'all',
                ],
                'permission' => [
                    'type' => 'select',
                    'label' => 'Permission',
                    'required' => true,
                    'id' => 'permission',
                    'class' => ['form-control'],
                    'name' => 'permission',
                    'option_values' => [
                        'administrator' => 'Administrator',
                        'content_creator' => 'Content Creator',
                        'manager' => 'Manager',
                        'authenticated' => 'Authenticated',
                        'anonymous' => 'Anonymous',
                    ],
                    'handler' => SelectField::class,
                    'default_value' => $view['permission'] ?? 'all',
                    'options' => [
                        'multiple' => 'multiple',
                    ]
                ]
            ]
        ];

        $form['submit'] = [
            'type' => 'submit',
            'name' => 'submit',
            'default_value' => 'Save View',
            'id' => 'submit',
            'class' => ['btn btn-primary'],
        ];

        return $form;
    }

    public function validateForm(array $form): void
    {
        foreach ($form as $field) {
            if ($field instanceof FieldBase && isset($field->getField()['inner_field'])) {
                $this->validate_recursive($field->getField()['inner_field']);
            }
            elseif ($field instanceof FieldBase && $field->getRequired() === 'required' && empty($field->getValue())) {
                $field->setError($field->getLabel().' is required');
                $this->validated = false;
            }
        }
    }

    private function validate_recursive(array &$fields): void
    {
        foreach ($fields as &$field) {
            if ($field instanceof FieldBase && isset($field->getField()['inner_field'])) {
                $this->validate_recursive($field->getField()['inner_field']);
            }
            elseif ($field instanceof FieldBase && $field->getRequired() === 'required' && empty($field->getValue())) {
                $field->setError($field->getLabel().' is required');
                $this->validated = false;
            }
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

            $data = array_map(function ($field) {
                return $field->getValue();
            },$form);
            $view_data = [
                ...$data['wrapper'],
                ...$data['page_settings'],
                'displays' => []
            ];
            $types = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
            $types = array_keys($types);

            $redirect = new RedirectResponse('/admin/structure/views');
            $redirect->setStatusCode(302);
            unset($data['submit']);

            $request = Service::serviceManager()->request;
            $view = ViewsManager::viewsManager()->getView($request->get('view_name', ''));
            $message = "Views successfully created!";
            $name = null;
            if (empty($view)) {
                $name = $view_data['name'] ?? '';
                if (!empty($name)) {
                    $name = str_replace(' ', '_', $name);
                    $name = 'view_'.strtolower($name);
                }
            }
            else {
                $view_data = array_merge($view, $view_data);
                $name = $view['machine_name'];
                $message = "View successfully updated!";
            }
            if (ViewsManager::viewsManager()->addView($name, $view_data)) {
                Messager::toast()->addMessage("$message");
                $redirect->send();
            }
        }
    }
}