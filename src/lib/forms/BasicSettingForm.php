<?php

namespace Simp\Core\lib\forms;

use Simp\Core\modules\config\ConfigManager;
use Simp\Default\DetailWrapperField;
use Simp\Fields\FieldBase;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class BasicSettingForm extends FormBase
{

    private bool $validated = true;
    public function getFormId(): string
    {
       return 'basic_setting';
    }

    public function buildForm(array $form): array
    {
        $config = ConfigManager::config()->getConfigFile('basic.site.setting');
        $form['site'] = [
            'type' => 'details',
            'label' => 'Site details',
            'name' => 'site',
            'id' => 'site',
            'class' => ['form-details'],
            'handler' => DetailWrapperField::class,
            'inner_field' => [
                'site_name' => [
                    'label' => 'Site name',
                    'type' => 'text',
                    'required' => true,
                    'name' => 'site_name',
                    'id' => 'site_name',
                    'class' => ['form-control'],
                    'default_value' => $config?->get('site_name'),
                ],
                'site_slogan' => [
                    'label' => 'Site slogan',
                    'type' => 'text',
                    'required' => true,
                    'name' => 'site_slogan',
                    'id' => 'site_slogan',
                    'class' => ['form-control'],
                    'default_value' => $config?->get('site_slogan'),
                ],
                'site_email' => [
                    'label' => 'Site email',
                    'type' => 'email',
                    'required' => true,
                    'name' => 'site_email',
                    'id' => 'site_email',
                    'class' => ['form-control'],
                    'default_value' => $config?->get('site_email'),
                ]
            ],
            'options' => [
                'open' => 'open'
            ]
        ];

        $form['front_page'] = [
            'type' => 'details',
            'label' => 'Front page',
            'name' => 'front_page',
            'id' => 'front_page',
            'class' => ['form-details'],
            'handler' => DetailWrapperField::class,
            'inner_field' => [
                'front_page_url' => [
                    'label' => 'Front page url',
                    'type' => 'text',
                    'name' => 'front_page_url',
                    'id' => 'front_page_url',
                    'class' => ['form-control'],
                    'default_value' => $config?->get('front_page_url'),
                ]
            ],
            'options' => [
                'open' => 'open'
            ]
        ];

        $form['error_pages'] = [
            'type' => 'details',
            'label' => 'Error pages',
            'name' => 'error_pages',
            'id' => 'error_pages',
            'class' => ['form-details'],
            'handler' => DetailWrapperField::class,
            'inner_field' => [
                'not_found_page' => [
                    'label' => 'Not found page',
                    'type' => 'text',
                    'required' => true,
                    'name' => 'not_found_page',
                    'id' => 'not_found_page',
                    'class' => ['form-control'],
                    'default_value' => $config?->get('not_found_page'),
                ],
                'access_denied_page' => [
                    'label' => 'Access denied',
                    'type' => 'text',
                    'required' => true,
                    'name' => 'access_denied_page',
                    'id' => 'access_denied_page',
                    'class' => ['form-control'],
                    'default_value' => $config?->get('access_denied_page'),
                ]
            ]
        ];

        $form['submit'] = [
            'type' => 'submit',
            'name' => 'submit',
            'id' => 'submit',
            'class' => ['btn', 'btn-primary'],
            'default_value' => 'Submit',
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        foreach ($form as $value) {
            if ($value instanceof FieldBase && $value->getRequired() === 'required' && empty($value->getValue())) {
                $this->validated = false;
            }
        }
    }

    public function submitForm(array $form): void
    {
        if ($this->validated) {
            unset($form['submit']);
            $basic_setting = array_map(function ($value) {
                return $value->getValue();
            }, $form);

            $settings = [
                ...$basic_setting['site'],
                ...$basic_setting['front_page'],
                ...$basic_setting['error_pages']
            ];

            ConfigManager::config()->addConfigFile('basic.site.setting', $settings);
            $redirect = new RedirectResponse('/admin/config/system/site-information');
            $redirect->send();
        }
    }
}