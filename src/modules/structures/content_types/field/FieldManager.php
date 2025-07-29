<?php

namespace Simp\Core\modules\structures\content_types\field;

use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\structures\content_types\field\fields\DragDropFieldBuilder;
use Simp\Core\modules\structures\content_types\field\fields\FieldSetBuilder;
use Simp\Core\modules\structures\content_types\field\fields\FileFieldBuilder;
use Simp\Core\modules\structures\content_types\field\fields\InputFieldBuilder;
use Simp\Core\modules\structures\content_types\field\fields\MarkUpFieldBuilder;
use Simp\Core\modules\structures\content_types\field\fields\ReferenceFieldBuilder;
use Simp\Core\modules\structures\content_types\field\fields\SelectFieldBuilder;
use Simp\Core\modules\structures\content_types\field\fields\TextAreaFieldBuilder;

class FieldManager
{
    protected array $supported_fields;

    public function __construct()
    {
        $this->supported_fields = [
            'text' => InputFieldBuilder::class,
            'number' => InputFieldBuilder::class,
            'email' => InputFieldBuilder::class,
            'date' => InputFieldBuilder::class,
            'datetime' => InputFieldBuilder::class,
            'datetime-local' => InputFieldBuilder::class,
            'time' => InputFieldBuilder::class,
            'month' => InputFieldBuilder::class,
            'week' => InputFieldBuilder::class,
            'checkbox' => InputFieldBuilder::class,
            'radio' => InputFieldBuilder::class,
            'tel' => InputFieldBuilder::class,
            'url' => InputFieldBuilder::class,
            'search' => InputFieldBuilder::class,
            'range' => InputFieldBuilder::class,
            'color' => InputFieldBuilder::class,
            'password' => InputFieldBuilder::class,
            'hidden' => InputFieldBuilder::class,
            'submit' => InputFieldBuilder::class,
            'reset' => InputFieldBuilder::class,
            'button' => InputFieldBuilder::class,
            'file' => FileFieldBuilder::class,
            'drag_and_drop' => DragDropFieldBuilder::class,
            'select' => SelectFieldBuilder::class,
            'simple_textarea' => TextAreaFieldBuilder::class,
            'ck_editor' => TextAreaFieldBuilder::class,
            'details' => FieldSetBuilder::class,
            'fieldset' => FieldSetBuilder::class,
            'conditional' => FieldSetBuilder::class,
            'reference' => ReferenceFieldBuilder::class,
            'markup' => MarkupFieldBuilder::class,
        ];
        $module_handler = ModuleHandler::factory();
        $extension_fields = $module_handler->getFieldExtension();
        if (!empty($extension_fields)) {
            $this->supported_fields = array_merge($this->supported_fields, $extension_fields);
        }
        ksort($this->supported_fields);
        $system = new SystemDirectory();
        $extension_file = $system->setting_dir . DIRECTORY_SEPARATOR . 'fields' . DIRECTORY_SEPARATOR . 'fields.php';
        if (file_exists($extension_file)) {
            $fields = include $extension_file;
            if (!is_array($fields)) {
                $this->supported_fields = [
                  ...$this->supported_fields,
                  ...$fields,
                ];
            }
        }
    }

    public function getFieldBuilderHandler(string $type): FieldBuilderInterface|null
    {
        /**@var FieldBuilderInterface $new**/
        $new = $this->supported_fields[$type] ? new $this->supported_fields[$type]() : null;
        $new->extensionInfo($type);
        return $new;
    }

    public function getSupportedFieldsType(): array
    {
        return array_keys($this->supported_fields);
    }

    public function getFieldInfo(string $type): array
    {
        $handler = $this->getFieldBuilderHandler($type);
        return  $handler?->extensionInfo($type);
    }

    public static function createFieldName(string $title): string
    {
        // Convert to lowercase
        $input = strtolower($title);

        // Replace all non-alphanumeric characters with underscores
        $input = preg_replace('/[^a-z0-9]+/', '_', $input);

        // Trim leading and trailing underscores
        return trim($input, '_');
    }

    public static function fieldManager(): FieldManager {
        return new self();
    }
}
