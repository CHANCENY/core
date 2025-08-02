<?php

namespace Simp\Core\components\form;

use Simp\Core\lib\forms\DatabaseForm;
use Simp\Core\lib\forms\ForgotPasswordForm;
use Simp\Core\lib\forms\ForgotPasswordResetForm;
use Simp\Core\lib\forms\LoginForm;
use Simp\Core\lib\forms\MongodbForm;
use Simp\Core\lib\forms\ProfileEditForm;
use Simp\Core\lib\forms\SiteConfigForm;
use Simp\Core\lib\forms\UserAccountEditForm;
use Simp\Core\lib\forms\UserAccountForm;
use Simp\FormBuilder\FormBuilder;
use Symfony\Component\Yaml\Yaml;

final class FormDefinitionBuilder
{
    protected string $defintion_storage;

    public function __construct() {
        $this->defintion_storage = __DIR__. DIRECTORY_SEPARATOR ."forms";
    }

    public function getForm(string $form_name, array $options = []): ?FormBuilder
    {
        $file = $this->defintion_storage . DIRECTORY_SEPARATOR . $form_name . '.yml';

        if (file_exists($file)) {

            $form = Yaml::parseFile($file);
            if (!empty($form['name'])) {
                $class = $this->getFormHandler($form['name']);

                if (!class_exists($class)) {
                    return null;
                }

                /**@var \Simp\FormBuilder\FormBase $form_object**/
                $form_object = new $class($options);
                $form_object->setFormMethod($form['method'] ?? 'POST');
                $form_object->setFormEnctype($form['enctype'] ?? 'multipart/form-data');
                $form_object->setFormAction($form['action'] ?? '');
                $form_object->setIsJsAllowed($form['is_silent'] ?? false);
                $form_object->setFormAcceptCharset($form['accept_charset'] ?? 'UTF-8');
                $form_object->setFormFields($form['fields'] ?? []);

                $builder = new FormBuilder($form_object);
                return $builder;
            }
        }
        return null;
    }

    public function getFormHandler(string $form_handler_name)
    {
        $handler = null;

        switch ($form_handler_name) {
            case 'database.form':
                $handler = DatabaseForm::class;
                break;
            case 'mongodb.configuration.form':
                $handler = MongodbForm::class;
                break;
            case 'site.form':
                $handler = SiteConfigForm::class;
                break;
            case 'user.entity.form':
                $handler = UserAccountForm::class;
                break;
            case 'user.entity.login.form':
                $handler = LoginForm::class;
                break;
            case 'user.entity.password.form':
                $handler = ForgotPasswordForm::class;
                break;
            case 'user.entity.reset.password.form':
                $handler = ForgotPasswordResetForm::class;
                break;
            case 'user.entity.profile.form':
                $handler = ProfileEditForm::class;
                break;
            case 'user.entity.edit.form':
                $handler = UserAccountEditForm::class;
                break;
            default:
                break;
        }
        return $handler;
    }

    public static function factory(): FormDefinitionBuilder {
        return new self();
    }
}