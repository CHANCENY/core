<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\modules\config\ConfigManager;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\timezone\TimeZone;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Core\modules\user\entity\User;
use Simp\Core\modules\user\roles\Role;
use Simp\Default\DetailWrapperField;
use Simp\Default\FieldSetField;
use Simp\Default\SelectField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service;

class UserAccountEditForm extends UserAccountForm
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function buildForm(array $form): array
    {
        
        $form = parent::buildForm($form);
        $request = Service::serviceManager()->request;
        $user = User::load($request->get('uid'));
        $profile = $user->getProfile();

        if ($profile->getTimeZone()) {
            $timezone = $profile->getTimeZone();
            $form['user_prefer_timezone']['default_value'] =   $timezone;
            $form['user_prefer_timezone']['description'] = "current timezone is " . $timezone;
        }
        $form['mail']['default_value'] = $user->getMail();
        $form['name']['default_value'] = $user->getName();
        $role_field_original = $form['roles'] ?? [];
        $role_field_original['label'] = "Select role to add to existing user roles";
        $status_field = [
            'name' => 'status',
            'type' => 'select',
            'id' => 'status',
            'label' => "Status",
            'class' => ['form-control'],
            'default_value' => $user->getStatus() === true ? 1 : 2,
            'option_values' => [
                2 => 'Blocked',
                1 => 'Active',
            ],
            'handler' => SelectField::class,
            'description' => 'This account is current '. ($user->getStatus() === true ? 'active' : 'blocked'),
        ];

        if (CurrentUser::currentUser()?->isIsAdmin()) {
            $roles = $user->getRoles();
            $form['roles'] = [
                'type' => 'fieldset',
                'name' => 'users_roles',
                'id' => 'users_roles',
                'class' => ['form-control'],
                'label' => 'Manage account roles from here. you can select to keep or remove the existing role from account.',
                'inner_field' => array(),
                'handler' => FieldSetField::class,
                'options' => [
                    'open' => 'open'
                ]
            ];
            foreach ($roles as $key=>$role) {
                if ($role instanceof Role) {
                    $role_field = [
                        'type' => 'select',
                        'name' => 'role_'.$key,
                        'id' => 'role_'.$key,
                        'class' => ['form-control'],
                        'label' => "Account has this role (". $role->getName() . ")",
                        'option_values' => [
                            'keep' => 'Keep',
                            'remove' => 'Remove',
                        ],
                        'handler' => SelectField::class,
                        'default_value' => "keep",
                    ];
                    $form['roles']['inner_field']['role_'.$key] = $role_field;
                }
            }
            $form['roles']['inner_field']['roles'] = $role_field_original;
            $form['status'] = $status_field;
        }

        $submit = $form['submit'];
        unset($form['submit']);
        unset($form['cond_password']);

        $form['submit'] = $submit;
        return $form;
    }

    public function validateForm(array $form): void
    {
    }

    public function submitForm(array $form): void
    {
        $request = Service::serviceManager()->request;
        $user = User::load($request->get('uid'));
        $profile = $user->getProfile();

        $updated_data = [
            'mail' => $form['mail']->getValue(),
            'name' => $form['name']->getValue(),
        ];

        if (CurrentUser::currentUser()?->isIsAdmin()) {
            $status = 0;
            if (!empty($form['status']->getValue())) {
                $status = $form['status']->getValue();
            }
            else {
                $status = $user->getStatus();
            }
            $updated_data['status'] = $status;
        }

        // Avoid duplication
        if (!empty($form['roles']->getValue())) {
            $roles = $form['roles']->getValue() ?? [];
            $roles = is_string($roles) ? [$roles] : $roles;
            $count = count($roles);
            $user_roles = $user->getRoles();
            for ($i = 0; $i < $count; $i++) {
                if (isset($roles['role_'.$i]) && $roles['role_'.$i] === 'remove') {
                    $this_role = $user_roles[$i];
                    if ($this_role instanceof Role) {
                        $this_role->delete();
                    }
                }
            }

            $user_roles = $user->getRoles();
            $new_roles = $roles['roles'] ?? "authenticated";
            $duplicate_flag = false;
            foreach ($user_roles as $user_role) {
                if ($user_role instanceof Role) {
                    if ($new_roles === $user_role->getRoleName()) {
                        $duplicate_flag = true;
                    }
                }
            }
            if (!$duplicate_flag) {
                User::addUserRole($new_roles, $user->getUid());
            }
        }

        $timezone = $profile->getTimeZone();
        $new_timezone_index = $form['user_prefer_timezone']->getValue();
        $timezone_object = new TimeZone();
        $list = array_keys( $timezone_object->getSimplifiedTimezone());
        sort($list);
        $new_timezone = $list[$new_timezone_index] ?? null;
        $profile->setTimeZone($new_timezone ?? $timezone);
        $profile->update();

        $user->setMail($updated_data['mail']);
        $user->setName($updated_data['name']);
        $user->setStatus((int) $updated_data['status'] === 1);
        if ($user->update()) {
            Messager::toast()->addMessage("User '" . $updated_data['name'] . "' account has been updated.");
            $redirect = new RedirectResponse($request->server->get('REDIRECT_URL'));
            $redirect->send();
        }
        else {
            Messager::toast()->addError("User '" . $updated_data['name'] . "' account could not be updated.");
        }
    }
}