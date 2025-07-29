<?php

namespace Simp\Core\modules\structures\content_types\field\fields;

use Simp\Core\components\reference_field\ReferenceField;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\content_types\field\FieldBuilderInterface;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Simp\Core\modules\structures\taxonomy\VocabularyManager;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ReferenceFieldBuilder implements FieldBuilderInterface
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
        $contentTypes = ContentDefinitionManager::contentDefinitionManager()->getContentTypes();
        $vocabularies = VocabularyManager::factory()->getVocabularies();
        $template = match ($field_type) {
            'reference' => 'default.view.basic.reference',
        };
        return View::view($template,['field'=>$field,'definition'=>$options, 'content_types'=>$contentTypes, 'vocabularies'=>$vocabularies]);
    }

    public function fieldArray(Request $request, string $field_type, string $entity_type): array
    {
        $this->field_type = $field_type;
        return match ($field_type) {
           'reference' => $this->parseBasicInputSetting($request, $entity_type)
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

            $reference_entity = null;
            if (!empty($data['reference_type']) && $data['reference_type'] === 'node') {
                $reference_entity = $data['reference_content_type'];
            }
            elseif (!empty($data['reference_type']) && $data['reference_type'] === 'user') {
                $reference_entity = $data['reference_type_user'];
            }
            elseif (!empty($data['reference_type']) && $data['reference_type'] === 'file') {
                $reference_entity = $data['reference_type_file'];
            }
            elseif (!empty($data['reference_type']) && $data['reference_type'] === 'term') {
                $reference_entity = $data['reference_type_term'];
            }

            $field_data['reference'] = [
                'type' => $data['reference_type'] ?? '',
                'reference_entity' => $reference_entity,
            ];
        }

        return $field_data;
    }

    public function getFieldHandler(): string
    {
        return match ($this->field_type) {
            'reference' => ReferenceField::class
        };
    }

}