<?php

namespace Simp\Core\modules\console;

use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\components\site\SiteManager;
use Simp\Core\modules\cron\CronCommand;
use Symfony\Component\Console\Application;

class Console
{
    protected array $custom_commands = [];
    protected array $default_commands = [];

    public function __construct()
    {
        $this->custom_commands = ModuleHandler::factory()->getConsoleCommands();
        $this->default_commands = [
            // Add cron command
            new CronCommand()
        ];
    }

    public function console(string $name = "Simp CMS"): Application
    {
        $site_name = SiteManager::factory()->get('site_name',$name);

        $application = new Application($site_name, '1.0.0');
        $merged_commands = array_merge($this->default_commands, $this->custom_commands);
        $application->addCommands($merged_commands);
        return $application;
    }

    public static function application(string $name = "Simp CMS"): Application
    {
        return (new Console())->console($name);
    }
}