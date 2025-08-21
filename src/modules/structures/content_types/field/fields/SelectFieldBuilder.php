<?php

namespace Simp\Core\modules\structures\content_types\field\fields;

use Simp\Core\components\basic_fields\SelectField;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\structures\content_types\field\FieldBuilderInterface;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class SelectFieldBuilder implements FieldBuilderInterface
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
        $template = match ($field_type) {
            'select' => 'default.view.basic.select',
        };
        return View::view($template,['field'=>$field]);
    }

    public function fieldArray(Request $request, string $field_type, string $entity_type): array
    {
        $this->field_type = $field_type;
        return match ($field_type) {
            'select' => $this->parseOptionInputFieldSetting($request, $entity_type)
        };
    }

    public function extensionInfo(string $type): array
    {
        return match ($type) {
            'select' => [
                'title' => 'Select',
                'type' => 'select',
                'description' => 'This field extension give support for select field'
            ]
        };
    }

    public function getFieldHandler(): string
    {
        return SelectField::class;
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
            $field_data['option_values'] = $data['options'] ?? ['none'=>'None'];
        }
        return $field_data;
    }
}
