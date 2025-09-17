<?php

namespace Simp\Core\extends\multi_site_support\src\Plugin;

use DI\DependencyException;
use DI\NotFoundException;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\themes\TwigResolver;
use Simp\Core\modules\services\Service;
use Symfony\Component\Yaml\Yaml;

class MultiSiteSupport
{
    protected array $sites = [];

    protected array $themes = [];
    private mixed $currentTheme;
    /**
     * @var mixed|null
     */
    private mixed $currentThemeHomeTemplate;
    private mixed $current_theme_files;

    public bool $is_default_theme = false;

    /**
     */
    public function __construct()
    {
        $sites_file = (new SystemDirectory())->setting_dir . DIRECTORY_SEPARATOR . 'multi.sites.support.yml';
        if (!file_exists($sites_file)) {
            touch($sites_file);
        }
        $this->sites = Yaml::parseFile($sites_file) ?? [];

        $system = new SystemDirectory();
        $themes_base = $system->theme_dir;

        $this->current_theme_files = [];
        $this->themes = [
            'default' => [
                'name' => 'Default',
                'version' => '1.0.0',
                'default' => true,
                'home_template' => 'default.view.home',
            ]
        ];
        $this->currentTheme = "default";
        $this->currentThemeHomeTemplate = null;

        $files = array_diff(scandir($themes_base) ?? [], ['.', '..']);

        if (!empty($files)) {

            foreach ($files as $file) {

                $full_path = $themes_base . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR .
                    $file.'.info.yml';

                if (file_exists($full_path)) {

                    $content = Yaml::parseFile($full_path);
                    if (!empty($content['name']) && !empty($content['version'])) {

                        $this->themes[$file] = $content;

                        if (!empty($content['default'])) {

                            $this->currentTheme = $file;
                            $this->currentThemeHomeTemplate = $content['home_template'] ?? null;

                        }
                    }
                }
            }

        }

        $this->is_default_theme = $this->currentTheme === 'default';
    }

    protected function recursive_dir_iterator($dir): void
    {
        $files = array_diff(scandir($dir) ?? [], ['.', '..']);
        foreach ($files as $file) {
            $full_path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($full_path)) {
                $this->recursive_dir_iterator($full_path);
            }
            elseif (file_exists($full_path) && pathinfo($full_path, PATHINFO_EXTENSION) === 'twig') {
                $key = $this->currentTheme. '.view.'. pathinfo($full_path, PATHINFO_FILENAME);
                $this->current_theme_files[$key] = new TwigResolver($full_path);
            }
        }
    }


    /**
     * Returns the list of sites.
     * @return array
     */
    public function getSites(): array
    {
        return $this->sites;
    }

    /**
     * Returns the site data for a specific site ID.
     * @param string $site_id
     * @return array|null
     */
    public function getSite(string $site_id): ?array
    {
        return $this->sites[$site_id] ?? null;
    }

    /**
     * Adds a new site to the list of sites and updates the configuration file.
     * @param array $site_data
     * @return bool|int
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function addSite(array $site_data): bool|int
    {
        $clean_site_id = null;
        // remove all special characters and replace with underscore
        $clean_site_id = preg_replace('/[^a-zA-Z0-9_]/', '_', $site_data['domain']);
        $clean_site_id = strtolower($clean_site_id);

        $this->sites[$clean_site_id] = $site_data;
        return file_put_contents(Service::get('system.directory')->setting_dir . DIRECTORY_SEPARATOR . 'multi.sites.support.yml', Yaml::dump($this->sites, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    /**
     * Removes a site from the list of sites and updates the configuration file.
     *
     * @param string $site_id The ID of the site to be removed.
     * @return bool Returns true on success, or false on failure.
     * @throws DependencyException If a required dependency cannot be resolved.
     * @throws NotFoundException If the service directory cannot be found.
     */
    public function removeSite(string $site_id): bool
    {
        unset($this->sites[$site_id]);
        return file_put_contents(Service::get('system.directory')->setting_dir . DIRECTORY_SEPARATOR . 'multi.sites.support.yml', Yaml::dump($this->sites, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    public function getSiteByDomain(string $domain): ?array
    {
        foreach ($this->sites as $site) {
            if ($site['domain'] === $domain) {
                return $site;
            }
        }
        return null;
    }

    public function getThemeByDomain(string $domain): ?array
    {
        foreach ($this->sites as $site) {
            if ($site['domain'] === $domain) {

                return $this->themes[$site['theme']];
            }
        }
        return null;
    }

    public function getThemeIdByDomain(string $domain): ?string
    {
        foreach ($this->sites as $site) {
            if ($site['domain'] === $domain) {
                return $site['theme'];
            }
        }
        return null;
    }

    public static function isMultiSiteSupportEnabled(): bool
    {
        return ModuleHandler::factory()->isModuleEnabled('multi_site_support');
    }

    public function getThemes(): array
    {
        return $this->themes;
    }

    public function getCurrentTheme(): mixed
    {
        return $this->currentTheme;
    }

    public function getCurrentThemeHomeTemplate(): mixed
    {
        return $this->currentThemeHomeTemplate;
    }

    public function getCurrentThemeFiles(): mixed
    {
        return $this->current_theme_files;
    }



}