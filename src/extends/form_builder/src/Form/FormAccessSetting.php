<?php

namespace Simp\Core\extends\form_builder\src\Form;

use Simp\FormBuilder\FormBase;

class FormAccessSetting extends FormBase
{

    public function getFormId(): string
    {
        return 'form-access-setting';
    }

    public function buildForm(array $form): array
    {
        $form = parent::buildForm($form);
        return $form;
    }

    public function validateForm(array $form): void
    {
        // TODO: Implement validateForm() method.
    }

    public function submitForm(array $form): void
    {
        // TODO: Implement submitForm() method.
    }
}