<?php

namespace Simp\Core\lib\cli;

use Simp\Core\lib\installation\SystemDirectory;

class CliManager extends SystemDirectory
{
    protected array $commands = [];

    public function __construct()
    {
        parent::__construct();
        $default_command = $this->setting_dir . DIRECTORY_SEPARATOR. 'defaults'
            .DIRECTORY_SEPARATOR.'cli'.DIRECTORY_SEPARATOR. 'commands.php';
        $this->commands = file_exists($default_command) ? include $default_command : [];
    }

    public function __get(string $name)
    {
        if (array_key_exists($name, $this->commands)) {
            return $this->commands[$name];
        }
        return null;
    }

    public function __call(string $name, array $arguments)
    {
        if (array_key_exists($name, $this->commands)) {
            return $this->commands[$name];
        }
        return null;
    }
}