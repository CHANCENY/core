<?php

namespace Simp\Core\components\site;

use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\memory\cache\Caching;
use Symfony\Component\Yaml\Yaml;

class SiteManager extends SystemDirectory
{
    protected array $basic_settings = [];

    public function __construct()
    {
        parent::__construct();
        $site_file = $this->setting_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'basic.site.setting.yml';
        if (file_exists($site_file)) {
            $this->basic_settings = Yaml::parseFile($site_file);
        }
        $GLOBALS['site_manager'] = $this;
    }

    public function get(string $key, $default = null)
    {
        return $this->basic_settings[$key] ?? $default;
    }

    public static function factory(): SiteManager
    {
        if (isset($GLOBALS['site_manager'])) {
            return $GLOBALS['site_manager'];
        }
        return new self();
    }
}