<?php

namespace Simp\Core\extends\page_builder\src\Field;

use Exception;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\image\src\Loader\Gallery;
use Simp\Core\extends\page_builder\src\Plugin\Page;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Simp\Fields\FieldBase;
use Simp\Fields\FieldRequiredException;
use Simp\Fields\FieldTypeSupportException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PageFieldBuilderField extends FieldBase
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
            'page_builder',
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

            if (isset($post[$field['name'].'_hidden_pids'])) {
                $value = $post[$field['name'].'_hidden_pids'];
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
        return !empty($this->submission['value']) ? $this->submission['value'] : $this->field['default_value'] ?? '';
    }

    public function get(string $field_name): float|int|bool|array|string|null
    {
        return $this->getValue();
    }

    /**
     * @param bool $wrapper
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    public function getBuildField(bool $wrapper = true): string
    {
        $module_handler = ModuleHandler::factory();
        $module_handler->attachLibrary('page_builder', 'page.builder.library');

        $pid = $this->getValue();
        $pid = is_array($pid) ? $pid[0] : $pid;


        $page = Page::load(is_numeric($pid) ? $pid : 0);

        $status = $page->getStatus() == 1 ? 'Yes' : 'No';

        $wrapper_id = $this->getName(). "-wrapper-".uniqid();

        $line = "";
        if ($page->getTitle()) {
            $line = "{$page->getTitle()} ({$page->getName()}) v{$page->getVersion()} status: active {$status}";
        }


        return View::view('default.view.field_field_page_builder', [
            'field' => $this,
            'page' => $page,
            'line' => $line,
            'wrapper_id' => $wrapper_id,
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

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function display(string $field_type, FieldBase $field, array $context): string
    {
        $context = ['definition'=>$field, ...$context];
        return trim(View::view("default.view.node.field.page.builder", $context));
    }
}