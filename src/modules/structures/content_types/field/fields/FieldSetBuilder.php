<?php

namespace Simp\Core\modules\structures\content_types\field\fields;

use Simp\Core\lib\themes\View;
use Simp\Core\modules\structures\content_types\field\FieldBuilderInterface;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Simp\Default\ConditionalField;
use Simp\Default\DetailWrapperField;
use Simp\Default\FieldSetField;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class FieldSetBuilder implements FieldBuilderInterface
{

    private string $field_type;

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function build(Request $request, string $field_type, array $options = []): string
    {
        $this->field_type = $field_type;
        $field = self::extensionInfo($field_type);
        $fields = FieldManager::fieldManager()->getSupportedFieldsType();
        // remove unsupported fields
        $fields = array_diff($fields,['fieldset','details','conditional']);
        $all = [];
        $html = [];
        foreach ($fields as $type) {
            $extension = FieldManager::fieldManager()->getFieldBuilderHandler($type);
            $all[$type] = $extension->extensionInfo($type);
            $html[$type] = $extension->build($request,$type);
        }
        $template = match ($field_type) {
            'fieldset','details' => 'default.view.basic.fieldset',
            'conditional' => 'default.view.basic.conditional',
        };
        return View::view($template,['field'=>$field,'fields'=>$all,'html'=>$html, 'content'=>$options]);
    }

    public function fieldArray(Request $request, string $field_type, string $entity_type): array
    {
        $this->field_type = $field_type;
        return match ($field_type) {
            'fieldset', 'details' => $this->parseFieldCollectionSetting($request, $entity_type),
            'conditional' => $this->parseFieldCollectionConditionalSetting($request, $entity_type)
        };
    }

    public function extensionInfo(string $type): array
    {
        return match ($type) {
            'fieldset' => [
                'title' => 'Fieldset',
                'type' => 'fieldset',
                'description' => 'This field extension give support for fieldset field'
            ],
            'details' => [
                'title' => 'Details',
                'type' => 'details',
                'description' => 'This field extension give support for details field'
            ],
            'conditional' => [
                'title' => 'Conditional',
                'type' => 'conditional',
                'description' => 'This field extension give support for conditional field'
            ]
        };
    }

    public function getFieldHandler(): string
    {
        return match ($this->field_type) {
            'details' => DetailWrapperField::class,
            'conditional'=> ConditionalField::class,
            default => FieldSetField::class
        };
    }

    private function parseFieldCollectionSetting(Request $request, string $entity_type): array
    {
        $data = $request->request->all();
        $field_data = [];
        if (!empty($data['title'])) {
            $field_data['label'] = $data['title'];
            $field_data['name'] = !empty($data['name']) ? $data['name'] : $entity_type .'_field_' . FieldManager::createFieldName($data['title']);
            $field_data['id'] = !empty($data['id']) ? $data['id'] : ( !empty($data['name']) ? $data['name'] : $entity_type .'_field_' . FieldManager::createFieldName($data['title']));
            $field_data['class'] = array_filter(explode(' ', $data['class'] ?? ''));
            $field_data['default_value'] = $data['default_value'] ?? '';
            $field_data['required'] = !empty($data['required']) && $data['required'] == 'on';
            $field_data['handler'] = $this->getFieldHandler();
            $field_data['limit'] = (int)($data['limit'] ?? 1);
            $field_data['type'] = $this->field_type;

            // Parse the inner fields
            $inner_fields = [];
            $largest_index = 0;
            foreach (array_keys($data) as $key) {
                $split = explode('_', $key);
                $index = (int)(end($split));
                if ($index >= $largest_index) {
                    $largest_index = $index;
                }
            }

            $largest_index = $largest_index === 0 ? 100 : $largest_index;
            for ($i = 0; $i <= $largest_index; $i++) {

                if (!empty($data['inner_type_' . $i])) {

                    if (empty($data['name'])) {
                        $handler = FieldManager::fieldManager()->getFieldBuilderHandler($data['inner_type_' . $i]);
                        $handler = $handler->getFieldHandler();
                        $inner_field = [
                            'label' => $data['inner_title_' . $i] ?? '',
                            'name' => $entity_type .'_field_' . FieldManager::createFieldName($data['inner_title_' . $i]),
                            'id' => !empty($data['inner_id_' . $i]) ? $data['inner_id_' . $i] : $entity_type .'_field_' . FieldManager::createFieldName($data['inner_title_' . $i]),
                            'class' => array_filter(explode(' ', $data['inner_class_' . $i] ?? '')),
                            'default_value' => $data['inner_default_value_' . $i] ?? '',
                            'required' => !empty($data['inner_required_' . $i]) && $data['inner_required_' . $i] == 'on',
                            'handler' => $handler,
                        ];

                    }
                    else {


                        if (!empty($data['name_'.$i])) {
                            $handler = FieldManager::fieldManager()->getFieldBuilderHandler($data['inner_type_' . $i]);
                            $handler = $handler->getFieldHandler();
                            $inner_field = [
                                'label' => $data['inner_title_' . $i] ?? '',
                                'name' =>  $data['name_'.$i],
                                'id' => !empty($data['inner_id_' . $i]) ? $data['inner_id_' . $i] : $data['name_'.$i],
                                'class' => array_filter(explode(' ', $data['inner_class_' . $i] ?? '')),
                                'default_value' => $data['inner_default_value_' . $i] ?? '',
                                'required' => !empty($data['inner_required_' . $i]) && $data['inner_required_' . $i] == 'on',
                                'handler' => $handler,
                            ];
                        }
                    }

                    if ($data['inner_type_' . $i] === 'checkbox') {
                        $inner_field['type'] = 'checkbox';
                        $inner_field['checkboxes'] = $data['inner_options_' . $i] ?? [];
                    }
                    elseif ($data['inner_type_' . $i] === 'radio') {
                        $inner_field['type'] = 'radio';
                        $inner_field['radios'] = $data['inner_options_' . $i] ?? [];
                    }
                    elseif ($data['inner_type_' . $i] === 'select') {
                        $inner_field['type'] = 'select';
                        $inner_field['option_values'] = $data['inner_options_' . $i] ?? [];
                    }
                    elseif ($data['inner_type_' . $i] === 'simple_textarea') {
                        $inner_field['type'] = 'textarea';
                    }
                    elseif ($data['inner_type_' . $i] === 'ck_editor') {
                        $inner_field['type'] = 'textarea';
                    }
                    else {
                        $inner_field['type'] = $data['inner_type_' . $i];
                    }
                    $inner_fields[$inner_field['name']] = $inner_field;
                }

            }
            $field_data['inner_field'] = $inner_fields;
        }
        return $field_data;
    }

    private function parseFieldCollectionConditionalSetting(Request $request, string $entity_type): array
    {
        $data = $request->request->all();
        $field_data = [];
        if (!empty($data['title'])) {
            $field_data = $this->parseFieldCollectionSetting($request, $entity_type);

           $events = $data['condition'] ?? [];
           $triggers = $data['trigger'] ?? [];
           $controlled = $data['controlled'] ?? [];
           $conditions = [];
           if (count($events) === count($triggers) && count($events) === count($controlled)) {
            foreach($events as $key=>$event) {
                $conditions[$triggers[$key]] = [
                    'event' => $event,
                    'receiver_field' => $controlled[$key],
                ];
            }
           }
           $field_data['conditions'] = $conditions;
        }
        return $field_data;
    }
}
