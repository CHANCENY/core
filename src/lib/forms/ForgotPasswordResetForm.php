<?php

namespace Simp\Core\lib\forms;

use Exception;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\user\password\PasswordManager;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service;

class ForgotPasswordResetForm extends FormBase
{

    protected bool $validated = true;

    public function getFormId(): string
    {
        return 'forgot_password_form';
    }

    public function buildForm(array $form): array
    {
        $form = parent::buildForm($form);
        return $form;
    }

    public function validateForm(array $form): void
    {
        if ($form['password']->getRequired() === 'required' && empty($form['password']->getValue())) {
            $form['password']->setError('Password cannot be empty');
            $this->validated = false;
        }
        if ($form['password_confirmation']->getRequired() === 'required' && empty($form['password_confirmation']->getValue())) {
            $form['password_confirmation']->setError('Confirm Password cannot be empty');
            $this->validated = false;
        }

        if ($form['password']->getValue() !== $form['password_confirmation']->getValue()) {
            $form['password_confirmation']->setError('Confirm Password does not match');
            $this->validated = false;
        }
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws Exception
     */
    public function submitForm(array $form): void
    {
        if ($this->validated) {
            $hash = Service::serviceManager()->request->get('hash');
            if (Caching::init()->has($hash)) {
                $user = Caching::init()->get($hash);
                $password = new PasswordManager($user->getUid());
                if ($password->changePassword($form['password']->getValue())) {
                    Messager::toast()->addMessage("Your password has been changed");
                    $redirect = new RedirectResponse('/');
                    $redirect->send();
                }
            }
        }
    }
}