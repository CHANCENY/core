<?php

namespace Simp\Core\modules\config;

use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\config\config\ConfigReadOnly;
use Symfony\Component\Yaml\Yaml;

class ConfigManager extends SystemDirectory
{
    protected string $config_location;
    protected array $config_files;

    public function __construct()
    {
        parent::__construct();
        $this->config_location = $this->setting_dir . DIRECTORY_SEPARATOR . 'config';
        if (!is_dir($this->config_location)) {
            mkdir($this->config_location);
        }
        $list = array_diff(scandir($this->config_location) ?? [], ['..', '.']);
        foreach ($list as $file) {
            $full_path = $this->config_location . DIRECTORY_SEPARATOR . $file;
            if (is_file($full_path)) {
                $name = pathinfo($full_path, PATHINFO_FILENAME);
                $content = Yaml::parse(file_get_contents($full_path));
                $this->config_files[$name] = $content;
            }
        }
    }

    public function addConfigFile(string $name, mixed $content): ConfigReadOnly
    {
        $full_path = $this->config_location . DIRECTORY_SEPARATOR . $name . '.yml';
        if (file_put_contents($full_path, Yaml::dump($content))) {
            $this->config_files[$name] = $content;
        }
        return new ConfigReadOnly($name, $content);
    }

    public function getConfigFile(string $name): ?ConfigReadOnly
    {
        $data = $this->config_files[$name] ?? [];
        if (empty($data)) {
            return null;
        }
        return new ConfigReadOnly($name, $data);
    }

    public function deleteConfigFile(string $name): bool
    {
        $full_path = $this->config_location . DIRECTORY_SEPARATOR . $name . '.yml';
        if (is_file($full_path)) {
            return unlink($full_path);
        }
        return false;
    }

    public static function config(): ConfigManager
    {
        return new self();
    }
}