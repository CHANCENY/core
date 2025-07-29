<?php

namespace Simp\Core\components\drag_and_drop_field;

use Exception;
use Simp\Core\modules\assets_manager\AssetsManager;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Simp\Fields\FieldBase;
use Simp\Fields\FieldRequiredException;
use Simp\Fields\FieldTypeSupportException;

class DragDropField extends FieldBase
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

        $this->validation_message = '';

        $supported_field_type = ['drag_and_drop', 'hidden'];

        if (!in_array($field['type'], $supported_field_type)) {
            throw new FieldTypeSupportException("Field type '{$field['type']}' is not supported with this class ".static::class);
        }

        $required = ['label', 'name', 'type'];

        foreach ($required as $field_key) {

            if (!isset($field[$field_key])) {
                throw new FieldRequiredException("Field key {$field_key} is required");
            }
        }

        $this->field = $field;

        if ($request_method === 'POST') {
            $field_name = $field['name'];
            if (str_ends_with($field_name, '[]')) {
                $field_name = substr($field_name, 0, -2);
            }
            $value = $post[$field_name] ?? null;
            if (!is_null($value)) {
                $value = is_array($value) ? reset($value) : $value;
                $this->submission['value'] = is_array($value)? $value : json_decode($value, true);
            }
        }

    }

    protected function readableMemory($size): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
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
        if (is_string($data) || is_scalar($data) || is_null($data)  || is_bool($data)) {
            return $data;
        }

        if (is_array($data)) {
            $fids = [];
            foreach ($data as $file) {
                if (isset($file['fid'])) {
                    $fids[] = $file['fid'];
                }
            }
            return $fids;
        }
        return $data;
    }

    public function get(string $field_name): float|int|bool|array|string|null
    {
        return $this->getValue();
    }

    public function getBuildField(bool $wrapper = true): string
    {
        $class = implode(' ', $this->getClassList());
        $name = $this->getName();
        $label = $this->getLabel();
        $values = json_encode($this->getValue());

        if (!empty($this->field['limit']) && $this->field['limit'] > 1) {
            $name .= '[]';
        }

        $options = "";
        foreach ($this->getOptions() as $key=>$option) {
            $options .= "{$key}='{$option}'";
        }

        $extensions = implode(', ', $this->field['settings'] ?? ['image/*']);
        $size = $this->readableMemory($this->field['settings']['allowed_file_size'] ?? 10485760);

        $id = $this->getId();
        $settings = json_encode($this->field['settings'] ?? [], JSON_PRETTY_PRINT);
        $uuid = uniqid();

        $script = AssetsManager::assetManager()->getAssetsFile('grag_drop.js', true);
        $script = str_replace('__UUID__', "#$uuid", $script);

        $hidden_field = "<input type='hidden' name='{$name}' value='{$values}'/>";

        $drop_html = <<<DROP
 <div class="drag-drop-container">
        <div class="upload-container">
            <div class="upload-area" id="dropZone">
                <div class="upload-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                </div>
                <div class="upload-text">
                    <h3>Drag & Drop files here</h3>
                    <p>or</p>
                    <label for="fileInput" class="file-button">Browse Files</label>
                    <input type="file" id="fileInput" multiple style="display:none;">
                </div>
                <p class="file-info">Supports all file of types ($extensions). Maximum file size: ($size).</p>
            </div>

            <div class="file-list-container">
                <h2>Selected Files</h2>
                <div id="fileList" class="file-list">
                    <p class="empty-message">No files selected</p>
                </div>
            </div>

            <div class="upload-actions">
                <button id="uploadBtn" class="upload-button" disabled>Upload Files</button>
                <button id="clearBtn" class="clear-button" disabled>Clear All</button>
            </div>
        </div>

        <div class="upload-progress-container" style="display:none;">
            <h3>Upload Progress</h3>
            <div class="progress-bar-container">
                <div class="progress-bar" id="totalProgress"></div>
            </div>
            <p class="progress-text">0%</p>
        </div>
        {$hidden_field}
    </div>
DROP;

        return <<<WRAPPER
<div class="field-wrapper field--{$this->getName()} js-form-field-{$this->getName()}" id="{$uuid}">
<label for="{$id}">{$label}</label>
{$drop_html}
<span class="field-description">{$this->getDescription()}</span>
<span class="field-message message-{$this->getName()}">{$this->validation_message}</span>
<noscript>$settings</noscript>
</div>
<script>
$script
</script>
WRAPPER;

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
