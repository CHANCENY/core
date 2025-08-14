<?php

namespace Simp\Core\modules\mail;

use Simp\Core\modules\config\ConfigManager;
use Simp\Mail\Mail\Envelope;

class MailManager
{
    protected \Simp\Mail\Mail\MailManager $mailManager;
    public function __construct()
    {
        $config = ConfigManager::config()->getConfigFile("site.smtp.setting");
        $this->mailManager = \Simp\Mail\Mail\MailManager::mailManager(smtp_array: [
            'host' => $config->get('smtp_host'),
            'port' => (int) $config->get('smtp_port'),
            'username' => $config->get('smtp_username'),
            'password' => $config->get('smtp_password'),
        ]);
    }

    public function addEnvelope(Envelope $envelope): static
    {
        $envelope->addHeader('Content-Type', 'text/html; charset=UTF-8');
       $this->mailManager->addEnvelope($envelope);
       return $this;
    }

    public function send(): bool|array|null
    {
        return $this->mailManager->processEnvelopes();
    }

    public static function mailManager(): MailManager
    {
        return new MailManager();
    }
}