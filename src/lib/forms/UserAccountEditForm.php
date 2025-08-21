<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\routes\Route;
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
    private bool $validated = true;
    private User $user;

    public function __construct()
    {
        parent::__construct();
        $this->user = User::load(Service::serviceManager()->request->get('uid'));

    }

    /**
     */
    public function buildForm(array $form): array
    {
        
        $form = parent::buildForm($form);

        // set default values
        $form['name']['default_value'] = $this->user->getName();
        $form['mail']['default_value'] = $this->user->getMail();

        $profile = $this->user->getProfile();

        $timezone = new  TimeZone();
        $list = array_keys($timezone->getSimplifiedTimezone());
        sort($list);

        $timezone_default = $timezone->getTimezone($profile->getTimeZone());

        foreach ($list as $key => $value) {
            if ($value == $profile->getTimezone()) {
                $timezone_default = $key;
            }
        }
        $form['user_prefer_timezone']['default_value'] = $timezone_default;

        $roles = array_map(fn($role) => $role->getName(), $this->user->getRoles());
        $form['roles']['default_value'] = $roles;
        $form['roles']['options']['multiple'] = 'multiple';

        $current_user = CurrentUser::currentUser();
        if (!$current_user->isIsAdmin()) {
            unset($form['roles']['option_values']['administrator']);
        }

        $form['status']['default_value'] = $this->user->getStatus() === true ? 1 : 0;

        return $form;
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function validateForm(array $form): void
    {
        foreach ($form as $field) {
            if ($field->getRequired() === 'required' && empty($field->getValue())) {
                $this->validated = false;
            }
        }

        $data = array_map(function($item) {
            return $item->getValue();
        },$form);
        if (!empty($data['cond_password']['password']) && !empty($data['cond_password']['password_confirm'])) {

            if ($data['cond_password']['password'] !== $data['cond_password']['password_confirm']) {
                $form['cond_password']->setError('Password and confirm password does not match.');
                $this->validated = false;
            }

            if (!CurrentUser::currentUser()->isIsAdmin()) {
                if (!password_verify($data['cond_password']['old_password'], $this->user->getPassword())) {
                    $this->validated = false;
                    $form['cond_password']['old_password']->setError('Old password is incorrect.');
                }
            }

        }
    }

    public function submitForm(array $form): void
    {
        if ($this->validated) {
           $data = array_map(function($item) {
                return $item->getValue();
            },$form);

           $profile = $this->user->getProfile();

           $this->user->setName(!empty($data['name']) ? $data['name'] : $this->user->getName());
           $this->user->setMail(!empty($data['mail']) ? $data['mail'] : $this->user->getMail());
           $this->user->setStatus(intval($data['status']));

            if (!empty($data['cond_password']['password']) && !empty($data['cond_password']['password_confirm']))
            {
                $this->user->setPassword(password_hash($data['cond_password']['password'], PASSWORD_BCRYPT));
                
            }
            
            
            $timezone = new  TimeZone();
            $list = array_keys($timezone->getSimplifiedTimezone());
            sort($list);

            $timezone_default = $data['user_prefer_timezone'];

            foreach ($list as $key => $value) {
                if ($key == $timezone_default) {
                    $timezone_default = $value;
                    break;
                }
            }

            $profile->setTimezone($timezone_default);

            $roleManger = $this->user->roleManager();

            if ($this->user->update() && $profile->update()) {

                $roleManger->deleteAll();
                $roleManger->appendRoles($data['roles']);
                Messager::toast()->addMessage("User account has been updated.");
                $redirect = new RedirectResponse(Route::url('system.account.view', ['uid' => $this->user->getUid()]));
                $redirect->send();
            }
        }
    }
}