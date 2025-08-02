<?php

namespace Simp\Core\lib\forms;

use Exception;
use Simp\Core\modules\user\entity\User;
use Simp\Core\modules\user\password\PasswordManager;
use Simp\FormBuilder\FormBase;

class ForgotPasswordForm extends FormBase
{

    protected bool $validated = true;
    public function getFormId(): string
    {
       return 'forgot_password';
    }

    public function buildForm(array $form): array
    {
        $form = parent::buildForm($form);
        return $form;
    }

    public function validateForm(array $form): void
    {
        if ($form['name']->getRequired() === 'required' && empty($form['name']->getValue())) {
            $form['name']->setError('Please enter your email address or username');
            $this->validated = false;
        }
    }

    /**
     * @throws Exception
     */
    public function submitForm(array $form): void
    {
        if ($this->validated) {
            $name = $form['name']->getValue();
            $clean_name = htmlspecialchars(strip_tags($name));
            $user = null;
            if (filter_var($name, FILTER_VALIDATE_EMAIL)) {
                $user = User::loadByMail($name);
            }
            else {
                $user = User::loadByName($name);
            }

            if ($user) {
                //TODO: send email with reset link
                $password = new PasswordManager($user->getUid());
                $password->sendForgotPasswordLink();
            }
        }
    }
}