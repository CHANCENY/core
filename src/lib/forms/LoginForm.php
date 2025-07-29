<?php

namespace Simp\Core\lib\forms;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\modules\auth\normal_auth\AuthUser;
use Simp\Core\modules\messager\Messager;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LoginForm extends FormBase
{

    /**
     * @var true
     */
    private bool $validated = false;

    public function getFormId(): string
    {
        return 'login_form';
    }

    public function buildForm(array &$form): array
    {
        $form['name'] = [
            'type' => 'text',
            'name' => 'name',
            'id' => 'name',
            'label' => 'Username or Email',
            'required' => true,
            'class' => ['form-control'],
        ];
        $form['password'] = [
            'type' => 'password',
            'name' => 'password',
            'id' => 'password',
            'label' => 'Password',
            'required' => true,
            'class' => ['form-control'],
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        if (!empty($form['name']->getValue()) || !empty($form['password']->getValue())) {
            $this->validated = true;
        }
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function submitForm(array &$form): void
    {
       if ($this->validated) {
           $name = $form['name']->getValue();
           $password = $form['password']->getValue();

           $auth = AuthUser::auth();
           if ($auth->authenticate($name, $password)) {
               $auth->finalizeAuthenticate();
               $user = $auth->getUser();
               Messager::toast()->addMessage("Welcome back {$user->getName()}!");
               $redirect = new RedirectResponse('/');
               $redirect->send();
           }
           else {
               Messager::toast()->addError("Wrong username or password");
           }
       }
    }
}