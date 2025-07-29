<?php

namespace Simp\Core\lib\forms;

use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\config\ConfigManager;
use Simp\Default\FieldSetField;
use Simp\Default\SelectField;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Yaml\Yaml;

class DevelopmentForm extends FormBase
{

    public function getFormId(): string
    {
        return 'logger';
    }

    public function buildForm(array &$form): array
    {
        $form['logger'] = [
            'type' => 'fieldset',
            'label' => 'Logger Settings',
            'name' => 'logger',
            'id' => 'logger',
            'class' => ['form'],
            'inner_field' => [
                'enabled' => [
                    'type' => 'select',
                    'label' => 'Logger Enabled',
                    'name' => 'enabled',
                    'id' => 'enabled',
                    'class' => ['form-control'],
                    'option_values' => [
                        'yes' => 'Yes',
                        'no' => 'No',
                    ],
                    'handler' => SelectField::class
                ]
            ],
            'handler' => FieldSetField::class

        ];
        $form['caching'] = [
            'type' => 'fieldset',
            'label' => 'Caching Settings',
            'name' => 'caching',
            'id' => 'caching',
            'class' => ['form'],
            'inner_field' => [
                'twig_caching' => [
                    'type' => 'select',
                    'label' => 'Twig Caching Enabled',
                    'name' => 'twig_caching',
                    'id' => 'twig_caching',
                    'class' => ['form-control'],
                    'option_values' => [
                        'yes' => 'Yes',
                        'no' => 'No',
                    ],
                    'handler' => SelectField::class
                ],
                'response_caching' => [
                    'type' => 'select',
                    'label' => 'Response Caching Enabled',
                    'name' => 'response_caching',
                    'id' => 'response_caching',
                    'class' => ['form-control'],
                    'option_values' => [
                        'yes' => 'Yes',
                        'no' => 'No',
                    ],
                    'handler' => SelectField::class
                ],
                'database_output_caching' => [
                    'type' => 'select',
                    'label' => 'Database Output Caching Enabled',
                    'name' => 'database_output_caching',
                    'id' => 'database_output_caching',
                    'class' => ['form-control'],
                    'option_values' => [
                        'yes' => 'Yes',
                        'no' => 'No',
                    ],
                    'handler' => SelectField::class
                ],
            ],
            'handler' => FieldSetField::class

        ];
        $form['submit'] = [
            'type' => 'submit',
            'name' => 'submit',
            'id' => 'submit',
            'default_value' => 'Submit',
            'class' => ['btn', 'btn-primary'],

        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        // TODO: Implement validateForm() method.
    }

    public function submitForm(array &$form): void
    {
        $settings = array_map(function ($setting) {
            return $setting->getValue();
        },$form);
        ConfigManager::config()->addConfigFile('development.setting', $settings);
        $system = new SystemDirectory();
        $schema = $system->schema_dir . DIRECTORY_SEPARATOR . 'manifest.yml';
        if (file_exists($schema)) {
            $content = Yaml::parseFile($schema);
            $content['twig_setting']['cache'] = $settings['caching']['twig_caching'] === 'yes';
            $content['twig_setting']['debug'] = $settings['logger']['enabled'] === 'no';
            file_put_contents($schema, Yaml::dump($content));
        }
        (new RedirectResponse('/admin/config'))->send();

    }
}