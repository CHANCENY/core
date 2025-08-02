<?php

namespace Simp\Core\lib\forms;

use Google\Service\ContainerAnalysis\Envelope;
use Simp\Core\modules\config\ConfigManager;
use Simp\Core\modules\mail\MailManager;
use Simp\Default\SelectField;
use Simp\Fields\FieldBase;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SiteSmtpForm extends FormBase
{

    private bool $validated = true;
    public function getFormId(): string
    {
        return 'sitesmtp';
    }

    public function buildForm(array $form): array
    {
        $config = ConfigManager::config()->getConfigFile("site.smtp.setting");
        $form['smtp_host'] = [
            'type'=>'text',
            'label'=>'SMTP Host',
            'class' => ['form-control'],
            'id'=>'smtp_host',
            'name'=>'smtp_host',
            'default_value' => $config?->get('smtp_host'),
            'required'=>true,
        ];
        $form['smtp_username'] = [
            'type'=>'text',
            'label'=>'SMTP Username',
            'class' => ['form-control'],
            'id'=>'smtp_username',
            'name'=>'smtp_username',
            'default_value' => $config?->get('smtp_username'),
            'required'=>true,
        ];
        $form['smtp_password'] = [
            'type'=>'text',
            'label'=>'SMTP Password',
            'class' => ['form-control'],
            'id'=>'smtp_password',
            'name'=>'smtp_password',
            'default_value' => $config?->get('smtp_password'),
            'required'=>true,
        ];
        $form['smtp_port'] = [
            'type'=>'number',
            'label'=>'SMTP Port',
            'class' => ['form-control'],
            'id'=>'smtp_port',
            'name'=>'smtp_port',
            'default_value' => $config?->get('smtp_port'),
            'required'=>true,
        ];
        $form['smtp_secure'] = [
            'type'=>'select',
            'label'=>'SMTP Secure',
            'class' => ['form-control'],
            'id'=>'smtp_secure',
            'name'=>'smtp_secure',
            'handler' => SelectField::class,
            'option_values' => [
                'ssl'=>'SSL',
                'tls'=>'TLS',
            ],
            'default_value' => $config?->get('smtp_secure'),
            'required'=>true,
        ];
        $form['submit'] = [
            'type'=>'submit',
            'name'=>'submit',
            'id'=>'submit',
            'default_value'=>'Submit',
            'class' => ['btn btn-primary'],
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {
        foreach ($form as $field) {
            if ($field instanceof FieldBase && $field->getRequired() === 'required' && empty($field->getValue())) {
                $this->validated = false;
            }
        }
    }

    public function submitForm(array $form): void
    {
       if ($this->validated) {
           $new_smtp = array_map(function ($item) {
               return $item->getValue();
           },$form);
          ConfigManager::config()->addConfigFile("site.smtp.setting", $new_smtp);
          $redirect = new RedirectResponse('/admin/config/smtp');

          // Do test
           $site = ConfigManager::config()->getConfigFile("basic.site.setting");
           $envelope2 = \Simp\Mail\Mail\Envelope::create("Testing Email", "<h1>Hello this is a test email</h1><p>ok let see the paragraph</p>");
           $envelope2->addToAddresses([$site?->get('site_email', $new_smtp['smtp_username'])]);
           MailManager::mailManager()->addEnvelope($envelope2)->send();

          $redirect->send();
       }
    }
}