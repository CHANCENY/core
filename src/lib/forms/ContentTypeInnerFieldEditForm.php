<?php

namespace Simp\Core\lib\forms;

use Google\Service\Batch\Message;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\reference_field\ReferenceField;
use Simp\Default\FileField;
use Simp\Default\BasicField;
use Simp\Default\SelectField;
use Simp\Default\FieldSetField;
use Simp\Default\TextAreaField;
use Simp\Default\ConditionalField;
use Simp\Default\DetailWrapperField;
use Simp\Core\modules\messager\Messager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\services\Service;

class ContentTypeInnerFieldEditForm extends ContentTypeFieldForm
{

    public function getFormId(): string
    {
        return 'ContentTypeFieldEditForm';
    }

    public function buildForm(array &$form): array
    {
        $request = Service::serviceManager()->request;
        $name = $request->get('machine_name');
        $field_name = $request->get('parent_name');

        $field = ContentDefinitionManager::contentDefinitionManager()->getField($name,$field_name);
        $field = $field['inner_field'][$request->get('field_name')] ?? [];
        if (!empty($field)) {

            $list = [];
            foreach ($field['options'] ?? [] as $key=>$other) {
             $list[$key] = $other;
            }
            $form['title'] = [
                'type' => 'text',
                'label' => 'Field Name',
                'id' => 'title',
                'required' => true,
                'class' => ['form-control'],
                'name' => 'title',
                'default_value' => $field['label'] ?? null,
            ];
            $form['type'] = [
                'type' => 'select',
                'label' => 'Field Type',
                'id' => 'name',
                'required' => true,
                'class' => ['form-control'],
                'handler' => SelectField::class,
                'option_values' => [
                    'text' => 'Text',
                    'number' => 'Number',
                    'date' => 'Date',
                    'datetime' => 'DateTime',
                    'datetime-local' => 'DateTime-Local',
                    'month' => 'Month',
                    'week' => 'Week',
                    'time' => 'Time',
                    'email' => 'Email',
                    'hidden' => 'Hidden',
                    'password' => 'Password',
                    'reset' => 'Reset',
                    'search' => 'Search',
                    'url' => 'URL',
                    'tel' => 'Tel',
                    'color' => 'Color',
                    'range' => 'Range',
                    'select' => 'Select',
                    'file' => 'File',
                    'textarea' => 'TextArea',
                    'reference' => 'Reference'
                ],
                'name' => 'name',
                'default_value' => $field['type'] ?? null,
            ];
            $form['description'] = [
                'type' => 'textarea',
                'label' => 'Description',
                'id' => 'description',
                'required' => true,
                'class' => ['form-control'],
                'name' => 'description',
                'handler' => TextareaField::class,
                'default_value' => $field['description'] ?? null,
            ];
            $form['option'] = [
                'type' => 'fieldset',
                'label' => 'Field options',
                'id' => 'option',
                'required' => true,
                'class' => ['form-control'],
                'handler' => FieldSetField::class,
                'inner_field' => [
                    'field_id' => [
                        'type' => 'text',
                        'label' => 'Field ID',
                        'id' => 'field_id',
                        'required' => true,
                        'class' => ['form-control'],
                        'name' => 'field_id',
                        'default_value' => $field['id'],
                    ],
                    'field_classes' => [
                        'type' => 'text',
                        'label' => 'Field Classes',
                        'id' => 'field_classes',
                        'class' => ['form-control'],
                        'name' => 'field_classes',
                        'description' => 'give css class in space separated',
                        'default_value' => implode(' ', $field['class'] ?? []),
                    ],
                    'field_required' => [
                        'type' => 'select',
                        'label' => 'Field Required',
                        'id' => 'field_required',
                        'required' => true,
                        'class' => ['form-control'],
                        'name' => 'field_required',
                        'option_values' => [
                            'yes' => 'Yes',
                            'no' => 'No',
                        ],
                        'handler' => SelectField::class,
                        'default_value' => $field['required'] === true ? 'yes' : 'no',
                    ],
                    'field_default' => [
                        'type' => 'text',
                        'label' => 'Field Default Value',
                        'id' => 'field_default',
                        'class' => ['form-control'],
                        'name' => 'field_default',
                        'default_value' => $field['default_value'] ?? null,
                    ],
                    'others' => [
                        'type' => 'textarea',
                        'label' => 'Others',
                        'id' => 'others',
                        'class' => ['form-control'],
                        'name' => 'others',
                        'description' => 'give other field options',
                        'handler' => TextareaField::class,
                        'default_value' => implode('\n',$list) ?? null,
                    ]
                ],
                'name' => 'option'
            ];
            $form['reference_settings'] = [
                'type' => 'details',
                'label' => 'Reference Field Settings',
                'id' => 'reference_settings',
                'class' => ['form-control'],
                'handler' => DetailWrapperField::class,
                'name' => 'reference_settings',
                'inner_field' => [
                    'type' => [
                        'type' => 'select',
                        'label' => 'Reference Type',
                        'id' => 'type',
                        'class' => ['form-control'],
                        'name' => 'type',
                        'default_value' => $field['reference']['type'] ?? 'node',
                        'option_values' => [
                            'node' => 'Node Entity',
                            'user'=> 'User Entity',
                        ],
                        'handler' => SelectField::class,
                    ],
                    'reference_entity' => [
                        'type' => 'text',
                        'label' => 'Content Type machine name if type is node entity, d name if type is user entity',
                        'id' => 'reference_entity',
                        'class' => ['form-control'],
                        'name' => 'reference_entity',
                        'description' => 'optional for user entity',
                        'default_value' => $field['reference']['reference_entity'] ?? null,
                    ]
                ]
            ];
            $form['submit'] = [
                'type' => 'submit',
                'default_value' => 'Save field',
                'id' => 'submit',
                'name' => 'submit',
                'class' => ['btn', 'btn-primary'],
            ];
        }
        return $form;
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
        $request = Service::serviceManager()->request;
        if ($this->validated) {
            $data = array_map(function ($item) {
                return $item->getValue();
            }, $form);

            $field = [
                'type' => $data['type'],
                'label' => $data['title'],
                'required' => $data['option']['field_required'] === 'yes',
                'class' => explode(' ', $data['option']['field_classes']),
                'default_value' => $data['option']['field_default'],
                'id' => $data['option']['field_id'],
            ];
            $options = $data['option']['others'];
            if (!empty($options)) {
                $list = explode('\n', $options);
                $options = [];
                foreach ($list as $item) {
                    $lis = explode('=', $item);
                    if (count($lis) == 2) {
                        $options[$lis[0]] = $lis[1];
                    } else {
                        $options[end($lis)] = end($lis);
                    }
                }
                $field['options'] = $options;
            }
            $field['handler'] = match ($data['type']) {
                'file' => FileField::class,
                'textarea' => TextareaField::class,
                'select' => SelectField::class,
                'fieldset' => FieldSetField::class,
                'details' => DetailWrapperField::class,
                'conditional' => ConditionalField::class,
                'reference' => ReferenceField::class,
                default => BasicField::class,
            };

            $persist = true;
            if (in_array($data['type'], ['details', 'fieldset', 'conditional'])) {
            
                if ($data['type'] === 'conditional') {
                    $field['conditions'] = [];
                }
                $persist = false;
            }

            if ($data['type'] === 'reference') {
                $field['reference'] = [
                    'type' => $data['reference_settings']['type'],
                    'reference_entity' => $data['reference_settings']['reference_entity'] ?? null,
                ];
            }

            $name_content = $request->get('machine_name');
            $name = str_replace(' ', '_', $field['label']);
            $name = 'field_'.strtolower($name);
    
            $persist_override = $field;
            if (in_array($data['type'], ['details', 'fieldset', 'conditional'])) {
                $persist_override = [];
            }

            $original_field = ContentDefinitionManager::contentDefinitionManager()
            ->getContentType($name_content)['fields'][$request->get('parent_name')] ?? [];

            if (empty($original_field)) {
                Messager::toast()->addError("Failed to update due to missing dependencies");
                $redirect = new RedirectResponse('/admin/structure/content-type/' . $name_content . '/manage');
                $redirect->send();
                exit;
            }
            $inner_field = $original_field['inner_field'][$request->get('field_name')] ?? [];
            $field = array_merge($inner_field,$field);
            $original_field['inner_field'][$request->get('field_name')] = $field;

            if (ContentDefinitionManager::contentDefinitionManager()->addField($name_content, $request->get('parent_name'), $original_field, $persist, $persist_override)) {
                Messager::toast()->addMessage("Field '$name' has been updated");
            }
            $redirect = new RedirectResponse('/admin/structure/content-type/' . $name_content . '/manage');
            $redirect->send();
        }
    }

}