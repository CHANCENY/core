<?php

namespace Simp\Core\extends\page_builder\src\Field;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\extends\image\src\field\GalleryField;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\structures\content_types\field\FieldBuilderInterface;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PageBuilderFieldBuilder implements FieldBuilderInterface
{

    private string $field_type;

    /**
     * @param Request $request
     * @param string $field_type
     * @param array $options
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    public function build(Request $request, string $field_type, array $options = []): string
    {
        $this->field_type = $field_type;
        $field = self::extensionInfo($field_type);
        $options['show_types'] = [
          'Iframe',
          'Embedded',
           "Page-link",
           "Pdf-link",
          'Normal',
        ];
        return View::view('default.view.field_page_builder', ['definition'=>$options, 'field'=> $field]);
    }

    public function fieldArray(Request $request, string $field_type, string $entity_type): array
    {
        $this->field_type = $field_type;
        return match ($field_type) {
            'page_builder' => $this->parseFileInputFieldSetting($request, $entity_type)
        };
    }

    public function extensionInfo(string $type): array
    {
        $this->field_type = $type;
        return match ($type) {
            'page_builder' => ['title' => 'Page Contents', 'description' => 'Field takes data from page builder','type' => 'page_builder'],
        };
    }

    public function getFieldHandler(): string
    {
        return PageFieldBuilderField::class;
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
                'show_as' =>$data['show'] ?? "normal",
            ];
        }
        return $field_data;
    }
}