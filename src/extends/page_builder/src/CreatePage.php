<?php

namespace Simp\Core\extends\page_builder\src;

use Simp\Core\components\markup_field\MarkUpField;
use Simp\Core\extends\page_builder\src\Plugin\PageConfigManager;
use Simp\Core\modules\messager\Messager;
use Simp\Default\FieldSetField;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CreatePage extends FormBase
{

    protected bool $validated = true;
    public function getFormId(): string
    {
        return 'create_page';
    }

    public function buildForm(array $form): array
    {
        $form = parent::buildForm($form);
        $form['field_wrapper'] = [
            'type' => 'fieldset',
            'label' => 'Create Page',
            'id' => 'field_wrapper',
            'class' => [],
            'name' => 'field_wrapper',
            'handler' => FieldSetField::class,
        ];

        $form['field_wrapper']['inner_field'] = [
            'markup' => [
                'type' => 'markup',
                'markup' => "<p>Give the page a title and click save to create a new page.</p>",
                'handler' => MarkupField::class,
                'name' => 'markup',
            ],
            'title' => [
                'type' => 'text',
                'label' => 'Title',
                'name' => 'title',
                'class' => [],
                'id' => 'title',
                'required' => true,
            ],
            'system_name' => [
                'type' => 'hidden',
                'label' => '',
                'name' => 'system_name',
                'class' => [],
                'id' => 'system_name',
                'required' => true,
            ]
        ];
        $form['submit'] = [
            'type' => 'submit',
            'name' => 'submit',
            'default_value' => 'Submit',
            'class' => ['btn btn-primary'],
            'id' => 'submit_button',
        ];

        $form['script'] = [
            'type' => 'markup',
            'markup' => "<script>
document.addEventListener('DOMContentLoaded', () => {
  const titleInput = document.getElementById('title');
  const systemInput = document.getElementById('system_name');
  const systemLabel = document.querySelector('label[for=system_name]');

  titleInput.addEventListener('input', () => {
    let val = titleInput.value.trim();

    if (val.length > 0) {
      // Show system name as text input
      systemInput.type = 'text';
      systemLabel.textContent = 'Machine Name';

      // Slugify title into system_name
      let slug = val
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^-+|-+$/g, '');

      systemInput.value = slug;
    } else {
      // Hide system name if title is empty
      systemInput.type = 'hidden';
      systemInput.value = '';
      systemLabel.textContent = '';
    }
  });
});
</script>",
            'handler' => MarkupField::class,
            'name' => 'script',
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        foreach ($form as $field) {
            if ($field->getRequired() === 'required' && empty($field->getValue())) {
                $this->validated = false;
            }
        }
    }

    public function submitForm(array $form): void
    {
        if ($this->validated) {
            $page = array_map(function ($field) { return $field->getValue(); },$form);
            if ($page_config = PageConfigManager::factory('')->addPage($page['field_wrapper']['system_name'], $page['field_wrapper']['title'],'','')) {
                $redirect = new RedirectResponse("/core/modules/page_builder/build?t={$page_config}");
                $redirect->setStatusCode(302);
                $redirect->send();
            }

        }
        else {
            Messager::toast()->addError("Failed to create page, please check the form");
        }
    }
}