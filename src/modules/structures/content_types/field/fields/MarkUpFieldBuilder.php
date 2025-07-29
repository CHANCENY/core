<?php

namespace Simp\Core\modules\structures\content_types\field\fields;

use Simp\Core\components\markup_field\MarkUpField;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\structures\content_types\field\FieldBuilderInterface;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class MarkUpFieldBuilder implements FieldBuilderInterface
{

    protected string $field_type;

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function build(Request $request, string $field_type, array $options = []): string
    {
       $this->field_type = $field_type;
       $field = $this->extensionInfo($this->field_type);
       return View::view('default.view.basic.markup',['field'=>$field,'definition'=>$options,'type'=>$field_type]);
    }

    public function fieldArray(Request $request, string $field_type, string $entity_type): array
    {
        $this->field_type = $field_type;

        $data = $request->request->all();
        $title = $data['title'] ?? '';
        $field_data = [];
        if (!empty($title)) {
            $field_data['label'] = $title;
            $field_data['type'] = $data['type'] ?? 'text';
            $field_data['name'] = $entity_type .'_field_'. FieldManager::createFieldName($title);
            $field_data['id'] = $data['id'] ?? $entity_type.'_field_'. FieldManager::createFieldName($title);
            $field_data['class'] = explode(' ', $data['class'] ?? '');
            $field_data['markup'] = $data['content'] ?? '';
            $field_data['required'] = false;
            $field_data['handler'] = $this->getFieldHandler();
            $field_data['limit'] = (int)($data['limit'] ?? 1);
        }
        return $field_data;
    }

    public function extensionInfo(string $type): array
    {
        $this->field_type = $type;
        return [
            'title' => 'Markup',
            'description' => 'Field that contain html markup this field wont be validated on POST',
            'type' => $type,
        ];
    }

    public function getFieldHandler(): string
    {
       return MarkUpField::class;
    }
}