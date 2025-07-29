<?php

namespace Simp\Core\extends\image\src\field;

use Exception;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\image\src\Loader\Gallery;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Simp\Fields\FieldBase;
use Simp\Fields\FieldRequiredException;
use Simp\Fields\FieldTypeSupportException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class GalleryField extends FieldBase
{
    private array $field;
    private array $submission;
    protected string $validation_message;

    /**
     * @throws Exception
     * @throws FieldRequiredException
     * @throws FieldTypeSupportException
     */
    public function __construct(array $field, string $request_method, array $post = [], array $params = [], array $files = [])
    {
        parent::__construct($field, $request_method, $post, $params, $files);

        $supported_field_type = [
            'image_gallery',
        ];

        $required = [
            'label',
            'id',
            'name',
        ];

        $this->validation_message = '';

        foreach ($required as $field_key) {
            if (!array_key_exists($field_key, $field)) {
                throw new FieldRequiredException('Field "' . $field_key . '" is required.');
            }
        }

        if (!in_array($field['type'], $supported_field_type)) {
            throw new FieldTypeSupportException('Field "' . $field['type'] . '" is not supported type.');
        }

        $this->field = $field;

        if ($request_method === 'POST') {

            $value = $post[$field['name']] ?? null;
            if (!empty($field['required']) && empty($value) && empty($field['default_value'])) {
                $this->validation_message = "This input field is mandatory.";
            }
            if ($value !== null) {
                $this->submission['value'] = $value;
            }

        }

        if ($request_method === 'GET' && !empty($params)) {

            $value = $params[$field['name']] ?? null;
            if ($value !== null) {
                $this->submission['value'] = $value;
            }
        }

    }

    public function getLabel(): string
    {
        return $this->field['label'] ?? '';
    }

    public function getName(): string
    {
        return $this->field['name'] ?? '';
    }

    public function getType(): string
    {
        return $this->field['type'] ?? '';
    }

    public function getId(): string
    {
        return !empty($this->field['id']) ? $this->field['id'] : FieldManager::createFieldName($this->getLabel());
    }

    public function getClassList(): array
    {
        return $this->field['class'] ?? [];
    }

    public function getRequired(): string
    {
        return !empty($this->field['required']) ? 'required' : '';
    }

    public function getOptions(): array
    {
        return $this->field['options'] ?? [];
    }

    public function getDefaultValue(): string|int|float|null|array|bool
    {
        return $this->field['default_value'] ?? '';
    }

    public function getValue(): string|int|float|null|array|bool
    {
        $data = !empty($this->submission['value']) ? $this->submission['value'] : $this->field['default_value'] ?? '';
        if (str_ends_with($data, ')')) {
            $value = explode('(', $data);
            $value = end($value);
            return substr($value, 0, -1);
        }
        return $data;
    }

    public function get(string $field_name): float|int|bool|array|string|null
    {
        return $this->getValue();
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function getBuildField(bool $wrapper = true): string
    {
        $class = implode(' ', $this->getClassList());
        $options = null;
        foreach ($this->getOptions() as $key=>$option) {
            $options .= $key . '="' . $option . '" ';
        }

        $id = $this->getId();
        $gallery = Gallery::factory();
        $images = $gallery->getImagesByPage(Service::serviceManager()->request->get('page', 1), 5);

        $module_handler = ModuleHandler::factory();
        $module_handler->attachLibrary('image', 'image.gallery.library');

        return View::view('default.view.field.image.field_gallery', [
            'field' => $this->field,
            'object' => $this,
            'images' => $images,
            'pager' => $gallery->getImagesCount()
        ]);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function __toString(): string
    {
        return $this->getBuildField();
    }

    public function setError(string $error): void
    {
        $this->validation_message = $error;
    }

    public function getDescription(): string
    {
        return $this->field['description'] ?? '';
    }

    public function getField(): array
    {
        return $this->field;
    }
}