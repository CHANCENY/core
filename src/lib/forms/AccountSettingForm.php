<?php

namespace Simp\Core\lib\forms;

use Simp\Core\modules\config\ConfigManager;
use Simp\Default\ConditionalField;
use Simp\Default\DetailWrapperField;
use Simp\Default\FieldSetField;
use Simp\Default\SelectField;
use Simp\Default\TextAreaField;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AccountSettingForm extends FormBase
{

    public function getFormId(): string
    {
       return 'account_setting';
    }

    public function buildForm(array $form): array
    {
        $config = ConfigManager::config()->getConfigFile('account.setting');
        $form['anonymous'] = [
            'type' => 'details',
            'label' => 'Anonymous Users',
            'name' => 'anonymous',
            'id' => 'anonymous',
            'class' => ['form-details'],
            'inner_field' => [
                'anonymous_name' => [
                    'type' => 'text',
                    'label' => 'Name',
                    'id' => 'anonymous_name',
                    'class' => ['form-control'],
                    'description' => 'The name used to indicate anonymous users.',
                    'name' => 'anonymous_name',
                    'default_value' => $config?->get('anonymous_name','Anonymous Users'),
                ]
            ],
            'options' => [
                'open' => 'open'
            ],
            'handler' => DetailWrapperField::class,
        ];

        $form['register'] = [
            'type' => 'details',
            'label' => 'Registration and cancellation',
            'name' => 'register',
            'id' => 'register',
            'class' => ['form-details'],
            'inner_field' => [
                'allow_account_creation' => [
                    'type' => 'select',
                    'label' => 'Who can register accounts?',
                    'id' => 'allow_account_creation',
                    'class' => ['form-control'],
                    'option_values' => [
                        'administrator' => 'Administrator only',
                        'visitor' => 'Visitors',
                        'visitor-pending' => 'Visitors, but administrator approval is required',
                    ],
                    'handler' => SelectField::class,
                    'name' => 'allow_account_creation',
                    'default_value' => $config?->get('allow_account_creation','administrator'),
                ],
                'verification_email' => [
                    'type' => 'select',
                    'label' => 'Require email verification when a visitor creates an account',
                    'id' => 'verification_email',
                    'description' => 'New users will be required to validate their email address prior to logging into the site, and will be assigned a system-generated password. With this setting disabled, users will be logged in immediately upon registering, and may select their own passwords during registration.',
                    'class' => ['form-control'],
                    'option_values' => [
                        'yes' => 'Yes',
                        'no' => 'No',
                    ],
                    'handler' => SelectField::class,
                    'name' => 'verification_email',
                    'default_value' => $config?->get('verification_email','no'),
                ],
                'password_strength' => [
                    'type' => 'select',
                    'label' => 'Password strength',
                    'id' => 'password_strength',
                    'class' => ['form-control'],
                    'option_values' => [
                        '' => 'None',
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ],
                    'handler' => SelectField::class,
                    'name' => 'password_strength',
                    'default_value' => $config?->get('password_strength','low'),
                ],
                'cancellation' => [
                    'type' => 'select',
                    'label' => 'When cancelling a user account',
                    'id' => 'cancellation',
                    'class' => ['form-control'],
                    'name' => 'cancellation',
                    'option_values' => [
                        'blocked' => 'Disable the account and keep its content.',
                        'unpublished' => 'Disable the account and unpublish its content.',
                        'deleted' => 'Delete the account and delete its content.',
                    ],
                    'default_value' => $config?->get('cancellation','blocked'),
                    'handler' => SelectField::class,
                ]
            ],
            'handler' => DetailWrapperField::class,
        ];

        $config = ConfigManager::config()->getConfigFile('basic.site.setting');

        $form['notifications'] = [
            'type' => 'email',
            'label' => 'Notification email address',
            'name' => 'notifications',
            'id' => 'notifications',
            'class' => ['form-control'],
            'description' => "The email address to be used as the 'from' address for all account notifications listed below. If 'Visitors, but administrator approval is required' is selected above, a notification email will also be sent to this address for any new registrations. 
            Leave empty to use the default system email address <i>({$config?->get('site_email')})</i>.",
            'default_value' => $config->get('notifications',$config?->get('site_email')),
        ];

        $config = ConfigManager::config()->getConfigFile('account.setting');

        $form['emails'] = [
            'type' => 'conditional',
            'label' => 'Emails',
            'name' => 'emails',
            'id' => 'emails',
            'class' => ['form-details'],
            'handler' => ConditionalField::class,
            'description'=> "Edit the welcome email messages sent to new member accounts created or reset password email and confirmation email. Available variables are: [site:name], [site:url], [user:display-name], [user:account-name], [user:mail],  [user:one-time-login-url], [user:cancel-url].",
            'inner_field' => [
                'account_creation' => [
                    'type' => 'checkbox',
                    'label' => 'Welcome (new user created)',
                    'id' => 'account_creation',
                    'class' => ['form-check-input'],
                    'name' => 'account_creation',
                     'option' => [
                         'checked' => !empty($config?->get('account_creation_message', null)) ? 'checked' : '',
                     ]
                ],
                'account_creation_message' => [
                    'type' => 'textarea',
                    'label' => 'Welcome (new user created)',
                    'id' => 'account_creation_message',
                    'class' => ['form-control'],
                    'name' => 'account_creation_message',
                    'option' => [
                        'rows' => 10,
                        'cols' => 10,
                    ],
                    'handler' => TextAreaField::class,
                    'default_value' => $config?->get('account_creation_message','welcome your account is created'),
                ],
                'account_activation' => [
                    'type' => 'checkbox',
                    'label' => 'Activate account',
                    'id' => 'account_activation',
                    'class' => ['form-check-input'],
                    'name' => 'account_activation',
                    'option' => [
                        'checked' => !empty($config?->get('account_activation_message', null)) ? 'checked' : '',
                    ]
                ],
                'account_activation_message' => [
                    'type' => 'textarea',
                    'label' => 'Activate account message',
                    'id' => 'account_activation_message',
                    'class' => ['form-control'],
                    'name' => 'account_activation_message',
                    'option' => [
                        'rows' => 10,
                        'cols' => 10,
                    ],
                    'handler' => TextAreaField::class,
                    'default_value' => $config?->get('account_activation_message','Hello user your account is activation'),
                ],
                'password_recovery' => [
                    'type' => 'checkbox',
                    'label' => 'Password recovery',
                    'id' => 'password_recovery',
                    'class' => ['form-check-input'],
                    'name' => 'password_recovery',
                    'option' => [
                        'checked' => !empty($config?->get('password_recovery_message', null)) ? 'checked' : '',
                    ]
                ],
                'password_recovery_message' => [
                    'type' => 'textarea',
                    'label' => 'Password recovery message',
                    'id' => 'password_recovery_message',
                    'class' => ['form-control'],
                    'name' => 'password_recovery_message',
                    'option' => [
                        'rows' => 10,
                        'cols' => 10,
                    ],
                    'handler' => TextAreaField::class,
                    'default_value' => $config?->get('password_recovery_message','Hello user you requested a password recovery'),
                ]
            ],
            'conditions' => [
                'account_creation' => [
                    'event' => 'change',
                    'receiver_field' => 'account_creation_message',
                ],
                'account_activation' => [
                    'event' => 'change',
                    'receiver_field' => 'account_activation_message'
                ],
                'password_recovery' => [
                    'event' => 'change',
                    'receiver_field' => 'password_recovery_message'
                ]
            ]
        ];

        $form['submit'] = [
            'type' => 'submit',
            'default_value' => 'Save Configuration',
            'id' => 'submit',
            'class' => ['btn', 'btn-primary'],
            'name' => 'submit',
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        // TODO: Implement validateForm() method.
    }

    public function submitForm(array $form): void
    {
        $account_settings = array_map(function ($account_setting) {
            return $account_setting->getValue();
        }, $form);

        ConfigManager::config()->addConfigFile('account.setting', $account_settings);
        $redirect = new RedirectResponse('/admin/config/people/accounts');
        $redirect->setStatusCode(302);
        $redirect->send();
    }
}