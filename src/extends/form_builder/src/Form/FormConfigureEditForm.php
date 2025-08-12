<?php

namespace Simp\Core\extends\form_builder\src\Form;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\markup_field\MarkUpField;
use Simp\Core\extends\form_builder\src\Plugin\FormConfigManager;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\messager\Messager;
use Simp\Default\TextAreaField;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class FormConfigureEditForm extends FormBase
{

    protected bool $validated = true;
    protected mixed $options;
    public function __construct(mixed $options = [])
    {
        parent::__construct($options);

    }

    public function getFormId(): string
    {
        return 'edit-form';
    }

    public function buildForm(array $form): array
    {
        $form = parent::buildForm($form);

        $form['editor_warning'] = [
            'type' => 'markup',
            'name' => 'editor_warning',
            'markup' => "⚠️ Warning: You are about to edit YAML directly. Changes here can affect how the system works. Make sure the format is valid and properly indented — even a small mistake can cause errors. Proceed only if you know what you’re doing",
            'handler' => MarkUpField::class
        ];
        $form['configuration'] = [
            'type' => 'textarea',
            'name' => 'configuration',
            'label' => 'Configuration',
            'class' => ['form-control'],
            'id' => 'configuration',
            'required' => true,
            'default_value' =>  Yaml::dump($this->options,Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
            'handler' => TextareaField::class,
            'options' => [
                'rows' => 10,
                'cols' => 50,
            ]
        ];

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
        /**@var \Simp\Fields\FieldBase $field */
       foreach ($form as $field) {

           if ($field->getRequired() === 'required' && empty($field->getValue())) {
               $field->setError('This field is required.');
               $this->validated = false;
           }
       }

        try{
            $config = Yaml::parse($form['configuration']->getValue());
        }catch (Throwable $e) {
            $form['configuration']->setError($form['configuration']->getError() . '<br>'.$e->getMessage());
            $this->validated = false;
        }

    }

    /**
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function submitForm(array $form): void
    {
        if ($this->validated) {
            $this->options = Yaml::parse($form['configuration']->getValue());
            if (FormConfigManager::factory()->updateForm($this->options['name'], $this->options)) {
                $redirect = new RedirectResponse(Route::fromRouteName('form_builder.list')->route_path);
                $redirect->setStatusCode(302);
                $redirect->send();
            }
        }
        else {
            Messager::toast()->addError('Form configuration is not valid');
        }
    }
}