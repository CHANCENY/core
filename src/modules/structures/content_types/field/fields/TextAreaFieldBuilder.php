<?php

namespace Simp\Core\modules\structures\content_types\field\fields;

use Simp\Core\components\basic_fields\TextAreaField;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\structures\content_types\field\FieldBuilderInterface;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TextAreaFieldBuilder implements FieldBuilderInterface
{

    private string $field_type;

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
            'simple_textarea' => 'default.view.basic.simple.textarea',
            'ck_editor' => 'default.view.basic.ck_editor.textarea',

        };
        return View::view($template,['field'=>$field, 'definition'=>$options]);
    }

    public function fieldArray(Request $request, string $field_type, string $entity_type): array
    {
        $this->field_type = $field_type;
        return match ($field_type) {
            'simple_textarea' => $this->parseSimpleTextAreaSettings($request, $entity_type),
            'ck_editor' => $this->parseCkEditorTextAreaSettings($request, $entity_type),
        };
    }

    public function extensionInfo(string $type): array
    {
        return match ($type) {
          'simple_textarea' => [
              'title' => 'Simple Textarea',
              'type' => 'simple_textarea',
              'description' => 'This field extension give support for textarea field'
          ] ,
          'ck_editor' => [
              'title' => 'CK editor textarea',
              'type' => 'ck_editor',
              'description' => 'This field extension give support for ck editor field'
          ]
        };
    }

    public function getFieldHandler(): string
    {
       return TextareaField::class;
    }

    private function parseSimpleTextAreaSettings(Request $request, string $entity_type): array
    {
        $data = $request->request->all();
        $field_data = [];
        if (!empty($data['title'])) {
            $field_data['label'] = $data['title'];
            $field_data['name'] = $entity_type .'_field_' . FieldManager::createFieldName($data['title']);
            $field_data['id'] = $data['id'] ?? $entity_type .'_field_' . FieldManager::createFieldName($data['title']);
            $field_data['class'] = explode(' ', $data['class'] ?? '');
            $field_data['default_value'] = $data['default_value'] ?? '';
            $field_data['required'] = !empty($data['required']) && $data['required'] == 'on';
            $field_data['handler'] = $this->getFieldHandler();
            $field_data['limit'] = (int)($data['limit'] ?? 1);
            $field_data['options'] = [
                'rows' => $data['rows'] ?? 10,
                'cols' => $data['cols'] ?? 10,
            ];
            $field_data['type'] = 'textarea';
        }
        return $field_data;
    }

    private function parseCkEditorTextAreaSettings(Request $request, string $entity_type): array
    {
        $data = $request->request->all();
        $field_data = [];
        if (!empty($data['title'])) {
            $field_data['label'] = $data['title'];
            $field_data['name'] = $entity_type .'_field_' . FieldManager::createFieldName($data['title']);
            $field_data['id'] = $data['id'] ?? $entity_type .'_field_' . FieldManager::createFieldName($data['title']);
            $field_data['class'] = explode(' ', $data['class'] ?? '');
            $field_data['class'][] = 'editor';
            $field_data['default_value'] = $data['default_value'] ?? '';
            $field_data['required'] = !empty($data['required']) && $data['required'] == 'on';
            $field_data['handler'] = $this->getFieldHandler();
            $field_data['limit'] = (int)($data['limit'] ?? 1);
            $field_data['options'] = [
                'rows' => $data['rows'] ?? 10,
                'cols' => $data['cols'] ?? 10,
            ];
            $field_data['type'] = 'textarea';
        }
        return $field_data;
    }
}
