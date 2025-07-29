<?php

namespace Simp\Core\modules\user\fields;

use Simp\Core\modules\assets_manager\AssetsManager;
use Simp\Fields\FieldBase;
use Simp\Fields\FieldRequiredException;
use Simp\Fields\FieldTypeSupportException;

class UserReferenceField extends FieldBase {

    private array $field;
    private array $submission;
    protected string $validation_message;

    public function __construct(array $field, string $request_method, array $post = [], array $params = [], array $files = [])
    {
        parent::__construct($field, $request_method, $post, $params, $files);

        $supported_field_type = [
            'user',
        ];

        $required = [
            'name',
            'id',
            'label'
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
        return $this->field['id'] ?? '';
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

    public function getBuildField(bool $wrapper = true): string
    {
        $class = implode(' ', $this->getClassList());
        $options = null;
        foreach ($this->getOptions() as $key => $option) {
            $options .= $key . '="' . $option . '" ';
        }
        $script = AssetsManager::assetManager()->getAssetsFile('user.js');

        if ($wrapper) {
            return <<<FIELD
<div class="field-wrapper field--{$this->getName()} js-form-field-{$this->getName()}">
    <label for="{$this->getId()}">{$this->getLabel()}</label>
    <input type="search" 
    name="{$this->getName()}" 
    id="{$this->getId()}" 
    class="{$class} js-form-field-{$this->getName()} field-field--{$this->getName()} js-form-field-{$this->getName()}"
     value="{$this->getValue()}" {$options}/>
     <span class="field-description">{$this->getDescription()}</span>
     <span class="field-message message-{$this->getName()}">{$this->validation_message}</span>
     <div class="filters" id="filters--{$this->getId()}"></div>
    <script>
        (function(){

            {$script}
             
            document.addEventListener('DOMContentLoaded',()=>{
                attachAutoFilterListener('{$this->getId()}', document.getElementById('entity_name').value);
            })
            
        })();
    </script>
</div>
FIELD;
        }

        return <<<FIELD
<label for="{$this->getId()}">{$this->getLabel()}
 <input type="{$this->getType()}" 
    name="{$this->getName()}" 
    id="{$this->getId()}" 
    class="{$class} js-form-field-{$this->getName()} field-field--{$this->getName()} js-form-field-{$this->getName()}"
     value="{$this->getValue()}" {$options}/>
     <span class="field-description">{$this->getDescription()}</span>
     <span class="field-message message-{$this->getName()}">{$this->validation_message}</span>
</label>
FIELD;
    }

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