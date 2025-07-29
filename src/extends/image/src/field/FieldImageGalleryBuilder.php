<?php

namespace Simp\Core\extends\image\src\field;

use Simp\Core\lib\themes\View;
use Simp\Core\modules\structures\content_types\field\FieldBuilderInterface;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class FieldImageGalleryBuilder implements FieldBuilderInterface
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
        $options['extensions'] = [
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/gif',
            'image/webp',

        ];
        return View::view('default.view.field_image_gallery_builder', ['definition'=>$options, 'field'=> $field]);
    }

    public function fieldArray(Request $request, string $field_type, string $entity_type): array
    {
        $this->field_type = $field_type;
        return match ($field_type) {
            'image_gallery' => $this->parseFileInputFieldSetting($request, $entity_type)
        };
    }

    public function extensionInfo(string $type): array
    {
        $this->field_type = $type;
        return match ($type) {
          'image_gallery' => ['title' => 'Image Gallery', 'description' => 'Image Gallery for attaching and upload images','type' => 'image_gallery'],
        };
    }

    public function getFieldHandler(): string
    {
        return GalleryField::class;
    }

    private function parseFileInputFieldSetting(Request $request, string $entity): array
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
            $field_data['settings'] = [
                'allowed_file_types' =>$data['allowed_types'],
                'allowed_file_size' => $data['max_size'] ?? 1000
            ];
        }
        return $field_data;
    }
}