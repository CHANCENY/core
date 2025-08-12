<?php

namespace Simp\Core\extends\form_builder\src\Form;

use Simp\FormBuilder\FormBase;

class SubmissionFormHandler extends FormBase
{
    protected bool $validated = true;
    protected mixed $options;
    protected array $fields;
    protected string $form_id;

    public function __construct(mixed $options = [])
    {
        parent::__construct($options);

        $this->fields = $options['fields'] ?? [];
        $this->form_id = $options['form_id'] ?? '';

    }

    public function getFormId(): string
    {
       return 'submission_form_'.$this->form_id;
    }

    public function buildForm(array $form): array
    {
        $form = parent::buildForm($form);
        $form = array_merge($form, $this->fields);
        foreach ($form as $e=>$field) {
            $form[$e]['handler'] = str_replace('\\\\', '\\', $field['handler']);
        }
        $form['submit'] = [
            'type' => 'submit',
            'name' => 'submit',
            'default_value' => 'Submit',
            'class' => ['btn btn-primary'],
            'id' => 'submit_button',
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        foreach ($form as $field) {
            if ($field->getRequired() === 'required' && empty($field->getValue())) {
                $field->setError('This field is required.');
                $this->validated = false;
            }
        }
    }

    public function submitForm(array $form): void
    {
        if ($this->validated) {
            dd($form);
        }
    }
}