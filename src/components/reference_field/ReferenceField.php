<?php

namespace Simp\Core\components\reference_field;

use Exception;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\structures\content_types\entity\Node;
use Simp\Core\modules\structures\content_types\field\FieldManager;
use Simp\Core\modules\structures\taxonomy\Term;
use Simp\Core\modules\user\entity\User;
use Simp\Fields\FieldBase;
use Simp\Fields\FieldRequiredException;
use Simp\Fields\FieldTypeSupportException;

class ReferenceField extends FieldBase
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
            'reference',
        ];

        $required = [
            'label',
            'id',
            'reference',
            'name',
        ];

        $this->validation_message = '';

        foreach ($required as $field_key) {
            if (!array_key_exists($field_key, $field)) {
                throw new FieldRequiredException('Field "' . $field_key . '" is required.');
            }
        }

        if ($field['reference']['type'] !== 'user' && $field['reference']['type'] !== 'node' && $field['reference']['type'] !== 'file' && $field['reference']['type'] !== 'term') {
            throw new Exception("Reference type must be 'user' or 'node' but given " . $field['reference']['type']);
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

            if (isset($post[$field['name'] . '_hidden'])) {
                $this->submission['value'] = json_decode($post[$field['name'] . '_hidden'], true);
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
        return $this->getValue();
    }

    public function getValue(): string|int|float|null|array|bool
    {
        $data = !empty($this->submission['value']) ? $this->submission['value'] : $this->field['default_value'] ?? [];
        if (!empty($this->field['limit']) && $this->field['limit'] == 1) {
            return $data[0];
        }
        return $data;
    }

    public function get(string $field_name): float|int|bool|array|string|null
    {
        return $this->getValue();
    }

    private function getItems(array $data): array
    {
        $items = [];
        if (!empty($this->field['reference']['type']) && $this->field['reference']['type'] == 'node') {

            foreach ($data as $item) {
                $node = Node::load($item);
                if ($node) {
                    $items[] = [
                        'id' => $node->getNid(),
                        'title' => $node->getTitle()
                    ];
                }
            }

        }

        elseif (!empty($this->field['reference']['type']) && $this->field['reference']['type'] == 'user') {
            foreach ($data as $item) {
                $user = User::load($item);
                if ($user) {
                    $items[] = [
                        'id' => $user->getUid(),
                        'title' => $user->getName()
                    ];
                }
            }
        }
        elseif (!empty($this->field['reference']['type']) && $this->field['reference']['type'] == 'term') {
            foreach ($data as $item) {
                $term = Term::load($item);
                if ($term) {
                    $items[] = [
                        'id' => $term['id'],
                        'title' => $term['label']
                    ];
                }
            }
        }
        elseif (!empty($this->field['reference']['type']) && $this->field['reference']['type'] == 'file') {
            foreach ($data as $item) {
                $file = File::load($item);
                if ($file) {
                    $items[] = [
                        'id' => $file->getFid(),
                        'title' => $file->getName()
                    ];
                }
            }
        }
        return $items;
    }
    public function getBuildField(bool $wrapper = true): string
    {
        $class = implode(' ', $this->getClassList());
        $options = null;
        foreach ($this->getOptions() as $key=>$option) {
            $options .= $key . '="' . $option . '" ';
        }

        $default_values = $this->getDefaultValue();
        $default_values = $this->getItems($default_values);
        $default_values = json_encode($default_values);

         $id = $this->getId();
        $wrapper_id = "wrapper-".uniqid();
         $callable = json_encode($this->field['reference'], JSON_PRETTY_PRINT);

         $script = <<<SCRIPT
<script>
 (function(){
  'use strict';

  var wrapper = $(`#{$wrapper_id}`);

  if (wrapper.length > 0) {
    const settings = JSON.parse(JSON.stringify($callable)); 
    const default_values = JSON.parse(JSON.stringify($default_values)); // [{id, title}, ...]
    
    var input = wrapper.find(`#{$id}`);
    var suggestionsBox = wrapper.find(".suggestions");
    var selectedItemsDiv = wrapper.find("#selectedItems");
    var hiddenField = wrapper.find(`input[name="{$this->getName()}_hidden"]`);

    // Keep selected IDs here
    let selected = [];

    // Global cache of reference data for rendering
    window.__reference_data = [];

    function updateHiddenField() {
      hiddenField.val(JSON.stringify(selected));
    }

    function renderSelectedItems() {
      selectedItemsDiv.empty();
      selected.forEach(itemId => {
        const item = window.__reference_data.find(d => d.id === itemId);
        if (item) {
          const tag = $(
            '<div class="tag">'+item.title+'<span data-id="'+ item.id+'">&times;</span></div>'
          );
          // Remove on click
          tag.find("span").on("click", function(){
            const id = parseInt($(this).data("id"));
            selected = selected.filter(v => v !== id);
            renderSelectedItems();
            updateHiddenField();
          });
          selectedItemsDiv.append(tag);
        }
      });
    }

    function showSuggestions(results) {
      window.__reference_data = window.__reference_data.concat(results)
        .filter((v,i,self) => self.findIndex(x => x.id === v.id) === i); 
      // ^ merge & dedupe

      suggestionsBox.empty();
      if (results.length === 0) {
        suggestionsBox.hide();
        return;
      }
      results.forEach(item => {
        if (!selected.includes(item.id)) {
          const div = $('<div class="suggestion-item">'+ item.title +'</div>');
          div.on("click", function(){
            selected.push(item.id);
            renderSelectedItems();
            updateHiddenField();
            suggestionsBox.hide();
            input.val("");
          });
          suggestionsBox.append(div);
        }
      });
      suggestionsBox.show();
    }

    // Input typing
    input.on("input", async function() {
      const typedValue = $(this).val().trim();
      if (typedValue.length > 0) {
        try {
          const results = await window.reference_caller(typedValue, settings);
          showSuggestions(results);
        } catch (err) {
          console.error("reference_caller failed:", err);
        }
      } else {
        suggestionsBox.hide().empty();
      }
    });

    // Click outside → close
    $(document).on("click", function(e) {
      if (!$(e.target).closest(wrapper).length) {
        suggestionsBox.hide();
      }
    });

    // ✅ Handle default values
    if (Array.isArray(default_values) && default_values.length > 0) {
      // Add defaults to selected IDs
      selected = default_values.map(v => v.id);

      // Store default data in cache for rendering
      window.__reference_data = default_values;

      // Render tags + update hidden field
      renderSelectedItems();
      updateHiddenField();
    }
  }
})();
</script>
SCRIPT;

         return <<<HTML
<div id="{$wrapper_id}" class="reference-field-wrapper">
<label for="{$id}">{$this->getLabel()}</label>
  <div id="selectedItems" class="selected-items"></div>
  <input 
    type="text"
    id="{$id}"
    class="suggest-input {$class} js-form-field-{$this->getName()} field-field--{$this->getName()} js-form-field-{$this->getName()} password"
    name="{$this->getName()}"  
    placeholder="Type to search..."
  >
  
  <span class="field-description">{$this->getDescription()}</span>
  <span class="field-message message-{$this->getName()}">{$this->validation_message}</span>
  
  <div class="suggestions"></div>
  
  <input type="hidden" name="{$this->getName()}_hidden" value="[]">
</div>
{$script}
HTML;

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

    public function display(string $field_type, FieldBase $field, array $context): string
    {
        $context['definition'] = $field;
        return trim(View::view("default.view.node.reference.link", $context));
    }
}