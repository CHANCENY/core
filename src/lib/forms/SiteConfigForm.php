<?php

namespace Simp\Core\lib\forms;

use Simp\Core\components\site\SiteManager;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\user\entity\User;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SiteConfigForm extends FormBase
{
    protected bool $validated = true;

    public function getFormId(): string
    {
        return 'site_config_form';
    }

    public function validateForm(array $form): void
    {
        foreach ($form as $field) {
            if ($field->getRequired() === 'required' && empty($field->getValue())) {
                $this->validated = false;
                $field->setError('This field is required');
            }
        }
    }

    public function buildForm(array $form): array
    {
        $form = parent::buildForm($form);

        $site = SiteManager::factory();

        if ($site->get('site_name')) {

            foreach ($form as $key=>&$field) {
                if ($site->get($key)) {
                    $field['default_value'] = $site->get($key);
                }
            }

            $unset_key = ['admin_password', 'confirm_password', 'timezone', 'admin_username', 'admin_email'];
            foreach ($unset_key as $key) {
                unset($form[$key]);
            }
        }
        return $form;
    }

    public function submitForm(array $form): void
    {
        if ($this->validated) {

            // Save site info
            $site_info = array(
                'site_name' => $form['site_name']->getValue(),
                'site_mail' => $form['site_mail']->getValue(),
                'site_slogan' => $form['site_slogan']->getValue(),
                'front_page_url' => $form['front_page_url']->getValue(),
                'not_found_page' => $form['not_found_page']->getValue(),
                'access_denied_page' => $form['access_denied_page']->getValue(),
            );

            $site = SiteManager::factory()->set($site_info);

            if ((!empty($form['admin_email']) && !empty($form['admin_username']) && !empty($form['admin_password'])) && htmlspecialchars(strip_tags($form['admin_password']?->getValue() ?? '')) === htmlspecialchars(strip_tags($form['confirm_password']?->getValue() ?? '')))
            {

                // create user
                $user = User::create(
                    [
                        'name' => htmlspecialchars(strip_tags($form['admin_username']->getValue())),
                        'mail' => htmlspecialchars(strip_tags($form['admin_email']->getValue())),
                        'password' => htmlspecialchars(strip_tags($form['admin_password']->getValue())),
                        'roles' => ['administrator'],
                        'time_zone' => htmlspecialchars(strip_tags($form['timezone']->getValue()))

                    ]
                );

                if ($user) {

                    $redirect = new RedirectResponse('/');
                    $redirect->send();
                    exit;
                }

            }

            $redirect = new RedirectResponse(Service::serviceManager()->service('request')->getRequestUri());
            $redirect->setStatusCode(302);
            $redirect->send();

        }
    }
}