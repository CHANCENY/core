<?php

namespace Simp\Core\modules\structures\content_types\field\fields;

use Simp\Core\components\drag_and_drop_field\DragDropField;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\structures\content_types\field\FieldBuilderInterface;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DragDropFieldBuilder implements FieldBuilderInterface
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
            'drag_and_drop' => 'default.view.basic.drag_and_drop',
        };
        return View::view($template,['field'=>$field,'definition'=>$options]);
    }

    public function fieldArray(Request $request, string $field_type, string $entity_type): array
    {
        $this->field_type = $field_type;
        return match ($field_type) {
            'drag_and_drop' => $this->parseFieldCollectionConditionalSetting($request, $entity_type)
        };
    }

    public function extensionInfo(string $type): array
    {
        return match ($type) {
            'drag_and_drop' => [
                'title' => 'Drag and Drop',
                'type' => 'drag_and_drop',
                'description' => 'This field extension give support for drag and drop field'
            ],
        };
    }

    public function getFieldHandler(): string
    {
        return match ($this->field_type) {
            'drag_and_drop' => DragDropField::class,
        };
    }

    private function parseFieldCollectionConditionalSetting(Request $request, string $entity_type): array
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
            $field_data['class'][] = "dropzone-hidden";
        }
        return $field_data;
    }

}