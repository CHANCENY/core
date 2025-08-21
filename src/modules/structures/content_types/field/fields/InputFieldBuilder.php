<?php

namespace Simp\Core\modules\structures\content_types\field\fields;

use Simp\Core\components\basic_fields\BasicField;
use Simp\Core\components\basic_fields\CheckboxField;
use Simp\Core\components\basic_fields\RadioField;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\structures\content_types\field\FieldBuilderInterface;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class InputFieldBuilder implements FieldBuilderInterface
{
    protected string $field_type;
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function build(Request $request, string $field_type, array $options = []): string
    {
        $this->field_type = $field_type;
        $field = self::extensionInfo($field_type);
        $template = match ($field_type) {
            'text', 'number', 'email', 'date', 'datetime', 'datetime-local', 'time', 'month', 'week', 'tel',
                'url', 'search', 'range', 'color', 'password', 'hidden', 'submit','reset','button' => 'default.view.basic.simple',
            'radio', 'checkbox' => 'default.view.basic.radio',
        };
        return View::view($template,['field'=>$field,'definition'=>$options]);
    }

    public function fieldArray(Request $request, string $field_type, string $entity_type): array
    {
        $this->field_type = $field_type;
        return match ($field_type) {
            'text', 'number', 'email', 'date', 'datetime', 'datetime-local', 'time', 'month', 'week', 'tel',
            'url', 'search', 'range', 'color', 'password', 'hidden', 'submit','reset','button' => $this->parseBasicInputSetting($request,$entity_type),
            'radio','checkbox' => $this->parseOptionInputFieldSetting($request, $entity_type)
        };
    }

    public function extensionInfo(string $type): array
    {
        $this->field_type = $type;
        return [
            'title' => \ucfirst(\str_replace('-', ' ', $type)),
            'type' => $type,
            'description' => 'This field extension give support for '.$type. ' type field'
        ];
    }

    private function parseBasicInputSetting(Request $request, $entity): array
    {
        $data = $request->request->all();
        $title = $data['title'] ?? '';
        $field_data = [];
        if (!empty($title)) {
            $field_data['label'] = $title;
            $field_data['type'] = $data['type'] ?? 'text';
            $field_data['name'] = $entity .'_field_'. FieldManager::createFieldName($title);
            $field_data['id'] = $data['id'] ?? $entity.'_field_'. FieldManager::createFieldName($title);
            $field_data['class'] = explode(' ', $data['class'] ?? '');
            $field_data['default_value'] = $data['default_value'] ?? '';
            $field_data['required'] = !empty($data['required']) && $data['required'] == 'on';
            $field_data['handler'] = $this->getFieldHandler();
            $field_data['limit'] = (int)($data['limit'] ?? 1);
        }
        return $field_data;
    }

    public function getFieldHandler(): string
    {
        return match ($this->field_type) {
            'text', 'number', 'email', 'date', 'datetime', 'datetime-local', 'time', 'month', 'week', 'tel',
            'url', 'search', 'range', 'color', 'password', 'hidden', 'submit','reset','button' => BasicField::class,
            'radio' => RadioField::class,
            'checkbox' => CheckboxField::class
        };
    }

    private function parseOptionInputFieldSetting(Request $request, string $entity_type): array
    {
        $data = $request->request->all();
        $field_data = [];
        if (!empty($data['title'])) {
            $field_data['label'] = $data['title'];
            $field_data['name'] = $entity_type .'_field_' . FieldManager::createFieldName($data['title']);
            $field_data['id'] = $data['id'] ?? $entity_type .'_field_' . FieldManager::createFieldName($data['title']);
            $field_data['class'] = explode(' ', $data['class'] ?? '');
            $field_data['default_value'] = $data['default_value'] ?? '';
            $field_data['type'] = $data['type'] ?? 'checkbox';
            $field_data['required'] = !empty($data['required']) && $data['required'] === 'on';
            $field_data['limit'] = (int)($data['limit'] ?? 1);
            $field_data['handler'] = $this->getFieldHandler();

            if ($field_data['type'] === 'radio') {
                $field_data['radios'] = $data['options'] ?? ['none'=>'None'];
            }
            else {
                $field_data['checkboxes'] = $data['options'] ?? ['none'=>'None'];
            }
        }
        return $field_data;
    }
}
