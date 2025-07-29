<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\structures\views\ViewsManager;
use Simp\Default\DetailWrapperField;
use Simp\Default\FieldSetField;
use Simp\Default\SelectField;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service;

class DisplayEditForm extends FormBase
{

    public function getFormId(): string
    {
        return 'display_edit_form';
    }

    public function buildForm(array &$form): array
    {
        $display = ViewsManager::viewsManager()->getDisplay(Service::serviceManager()->request->get('display'));

        $form['display_wrapper'] = [
            'type'=> 'fieldset',
            'name'=> 'display_wrapper',
            'class' => ['form-wrapper'],
            'id'=> 'display_wrapper',
            'label'=> 'Display Information',
            'inner_field' => [
                'name' => [
                    'type' => 'text',
                    'label'=> 'Display Name',
                    'required' => true,
                    'id'=> 'name',
                    'name' => 'name',
                    'class' => ['form-control'],
                    'default_value' => $display['name'],
                ],
                'response_type' => [
                    'type' => 'select',
                    'label'=> 'Response Type',
                    'required' => true,
                    'id'=> 'response_type',
                    'option_values' => [
                        'text/html' => 'HTML',
                        'application/json' => 'JSON',
                    ],
                    'default_value' => $display['response_type'],
                    'handler' => SelectField::class,
                    'name'=> 'response_type',
                ],
                'template' => [
                    'type' => 'text',
                    'label'=> 'Custom Template',
                    'id'=> 'template',
                    'name' => 'template',
                    'class' => ['form-control'],
                    'default_value' => $display['template'],
                ]
            ],
            'handler' => FieldSetField::class,
        ];
        $form['permission_wrapper'] = [
            'type'=> 'details',
            'name'=> 'permission_wrapper',
            'class' => ['form-wrapper'],
            'id'=> 'permission_wrapper',
            'label'=> 'Permissions',
            'inner_field' => [
                'permission' => [
                    'type' => 'select',
                    'label'=> 'Permission',
                    'required' => true,
                    'id'=> 'permission',
                    'name'=> 'permission',
                    'class' => ['form-control'],
                    'default_value' => $display['permission'],
                    'handler' => SelectField::class,
                    'option_values' => [
                        'administrator' => 'Administrator',
                        'content_editor' => 'Content Editor',
                        'manager' => 'Manager',
                        'authenticated' => 'Authenticated',
                        'anonymous' => 'Anonymous',
                    ],
                    'options' => [
                        'multiple' => 'multiple'
                    ]
                ]
            ],
            'handler' => DetailWrapperField::class
        ];

        $form['submit'] = [
            'type' => 'submit',
            'name' => 'submit',
            'id'=> 'submit',
            'default_value' => 'Submit',
            'class' => ['btn btn-primary'],
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
    public function submitForm(array &$form): void
    {
        $data = array_map(fn($item) => $item->getValue(), $form);
        $display = ViewsManager::viewsManager()->getDisplay(Service::serviceManager()->request->get('display'));

        $display['name'] = !empty($data['display_wrapper']['name']) ? $data['display_wrapper']['name'] : $display['name'];
        $display['response_type'] = !empty($data['display_wrapper']['response_type']) ? $data['display_wrapper']['response_type'] : $display['response_type'];
        $display['template'] = !empty($data['display_wrapper']['template']) ? $data['display_wrapper']['template'] : $display['template'];

        $display['permission'] = !empty($data['permission_wrapper']['permission']) ?
            $data['permission_wrapper']['permission'] : $display['permission'];

        if (ViewsManager::viewsManager()->addFieldDisplay($display['display_name'],$display)) {
            Messager::toast()->addMessage("Display settings updated");
        }
        else {
            Messager::toast()->addMessage("Display settings not updated");
        }
        $redirect = new RedirectResponse('/admin/structure/views/view/'.Service::serviceManager()->request->get('view_name').'/displays');
        $redirect->setStatusCode(302);
        $redirect->send();
    }
}