<?php

namespace Simp\Core\lib\forms;

use Simp\Core\modules\mongodb\ClientCredentials;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MongodbForm extends FormBase
{

    protected bool $validated = true;

    public function getFormId(): string
    {
        return 'mongodb';
    }

    public function buildForm(array $form): array
    {
        return parent::buildForm($form);
    }

    public function validateForm(array $form): void
    {
        foreach ($form as $field) {
            if ($field->getRequired() === 'required' && empty($field->getValue())) {
                $this->validated = false;
                $field->setError('This field is required');
            }
        }
    }

    public function submitForm(array $form): void
    {
        if ($this->validated) {
            $data = array_map(function($value){ return $value?->getValue(); }, $form);
            if (!empty($data['submit'])){
                unset($data['submit']);
            }

            if (ClientCredentials::credentials()->saveCredentials($data)) {
                $redirect = new RedirectResponse('/core/site-config.php');
                $redirect->send();
            }
        }
    }
}