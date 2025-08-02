<?php

namespace Simp\Core\extends\auto_path\src\form;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\markup_field\MarkUpField;
use Simp\Core\extends\auto_path\src\path\AutoPathAlias;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Default\DetailWrapperField;
use Simp\Default\FieldSetField;
use Simp\Default\SelectField;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CreateAutoPathForm extends FormBase
{

    protected bool $validated = true;
    public function getFormId(): string
    {
       return 'create_auto_path';
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function buildForm(array $form): array
    {
        $content_types = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
        $options = array_keys($content_types);
        $values = array_map(function($item){ return $item['name']; },$content_types);
        $form['type'] = [
            'type' => 'select',
            'label' => 'Type',
            'name' => 'type',
            'id' => 'type',
            'class' => ['form-control'],
            'option_values' => array_combine($options,$values),
            'handler' => SelectField::class,
            'required' => true,
        ];

        $form['fields_wrapper'] = [
            'type'=> 'fieldset',
            'label'=> 'Available Fields Tokens',
            'id' => 'fields_wrapper',
            'class' => [],
            'name'=> 'fields_wrapper',
            'handler' => FieldSetField::class,
        ];

        $field_names = [];
        $function = function($fields, &$field_names) use (&$function): void {
            foreach ($fields as $field) {
                if (!empty($field['inner_field'])) {
                    $function($field['inner_field'], $field_names);
                }
                else {
                    $field_names[] = $field['name'];
                }
            }
        };

        foreach ($content_types as $content_type) {
            $function($content_type['fields'] ?? [], $field_names);
            $appended = ['title', 'nid', ...$field_names];
            $tokens = '';
            foreach ($appended as $item) {
                if ($item !== 'nid' && $item !== 'title') {
                    $tokens .= "<li>[node:field?".$item."]</li>";
                }
                else {
                    $tokens .= "<li>[node:".$item."]</li>";
                }
            }
            $field_names = [];
            $form['fields_wrapper']['inner_field'][$content_type['machine_name'].'_details'] = [
                'type' => 'details',
                'name' => $content_type['machine_name'].'_details',
                'id' => $content_type['machine_name'].'_details',
                'class' => ['form-control'],
                'label' => 'Tokens For Type ('. $content_type['name'].')',
                'handler' => DetailWrapperField::class,
            ];
            $form['fields_wrapper']['inner_field'][$content_type['machine_name'].'_details']['inner_field'][$content_type['machine_name']] = [
                'type' => 'markup',
                'label' => '',
                'markup' => "<ul>".$tokens."</ul>",
                'name' => $content_type['machine_name'],
                'handler' => MarkupField::class
            ];
        }
        $form['path'] = [
            'type' => 'text',
            'label' => 'Path',
            'name' => 'path',
            'class' => [],
            'id' => 'path',
            'description' => 'Give the path pattern to the auto path',
            'required' => true,
        ];

        $routes = [];
        $routes = Caching::init()->get('system.routes.keys');
        $mapped_routes = array();

        foreach ($routes as $route) {
            $route = Caching::init()->get($route);
            $mapped_routes[$route->route_id] = $route->route_title;
        }

        $form['route'] = [
          'type' => 'select',
          'label' => 'Route',
          'name' => 'route',
          'id' => 'route',
          'class' => ['form-control'],
          'option_values' => $mapped_routes,
          'handler' => SelectField::class,
          'required' => true,
          'default_value' => 'system.structure.content.node',
          'description' => 'Auto path alias does`t have a route controller so it will be redirected to the default route controller selected here.'
        ];

        $form['submit'] = [
            'type' => 'submit',
            'name' => 'submit',
            'default_value' => 'Submit',
            'class' => ['btn btn-primary'],
            'id' => 'submit_button',
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        if (empty($form['path']->getValue())) {
            $form['path']->setError('Path is required.');
            $this->validated = false;
        }
        if (empty($form['type']->getValue())) {
            $form['type']->setError('Type is required.');
            $this->validated = false;
        }

        if (empty($form['route']->getValue())) {
            $form['route']->setError('Route is required.');
        }
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
        if ($this->validated) {
            $pattern = $form['path']->getValue();
            $type = $form['type']->getValue();
            $route = $form['route']->getValue();
            if (AutoPathAlias::factory()->addAlias($pattern, $type,$route)) {
                Messager::toast()->addMessage("Auto Path alias '$pattern' has been created");
            }
            $redirect = new RedirectResponse('/admin/auto-path/list');
            $redirect->setStatusCode(302);
            $redirect->send();
        }
    }
}