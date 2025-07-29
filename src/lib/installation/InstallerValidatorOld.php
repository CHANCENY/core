<?php

namespace Simp\Core\lib\installation;

use Exception;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverCheckException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheDriverNotFoundException;
use Phpfastcache\Exceptions\PhpfastcacheExtensionNotInstalledException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidConfigurationException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidTypeException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\lib\file\file_system\stream_wrapper\SettingStreamWrapper;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\routes\Route;
use Simp\Core\lib\themes\TwigResolver;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\logger\ErrorLogger;
use Simp\Core\modules\theme\ThemeManager;
use Simp\StreamWrapper\WrapperRegister\WrapperRegister;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * @class This class is for managing site-set-up checking requirements.
 */

class InstallerValidatorOld extends SystemDirectory {

    public object $installer_schema;

    public function __construct() {

        parent::__construct();
        $schema_file = $this->schema_dir."/booter.yml";
        if (!file_exists($schema_file)) {
            die("Booter file does not exist");
        }
        $this->installer_schema = Yaml::parseFile($schema_file, Yaml::PARSE_OBJECT_FOR_MAP);

        // Run
        $globals = [
            'database',
            'caching',
            'stream_wrapper',
            'session_store',
            'system_store',
            'request_start_time'
        ];
        foreach ($globals as $value) {
            $GLOBALS[$value] = null;
        }
        $GLOBALS['system_store'] = $this;
        $GLOBALS['request_start_time'] = microtime(true);
    }

    /**
     * @return void
     */
    public function setUpFileSystem(): void {

        $streams = $this->installer_schema->streams;

        // Register StreamWrapper
        foreach ($streams as $wrapper) {
            $wrapper = (array) $wrapper;
            $wrapper_name = key($wrapper);
            $wrapper_handler = $wrapper[$wrapper_name];
            WrapperRegister::register($wrapper_name, $wrapper_handler);
        }

        // settings directory creation.
        if (!is_dir($this->setting_dir)) {
            mkdir($this->setting_dir, 0777, true);
        }

        //TODO: Create all need directories.
        foreach ($this->toArray() as $key=>$directory) {
            if (is_string($directory) && !is_dir($directory) && str_ends_with($key, '_dir')) {
                mkdir($directory, 0777, true);
            }
        };

        // Move the default_files
        $default = __DIR__ . '/default_installation_configs';
        $directory_list = array_diff(scandir($default), ['..', '.']);

        foreach ($directory_list as $directory) {
            $full_path = $default . DIRECTORY_SEPARATOR . $directory;
            $this->recursive_mover($full_path, $directory);
        }
    }

    /**
     * @throws PhpfastcacheDriverNotFoundException
     * @throws PhpfastcacheInvalidConfigurationException
     * @throws PhpfastcacheExtensionNotInstalledException
     * @throws PhpfastcacheDriverCheckException
     * @throws PhpfastcacheInvalidTypeException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function setUpSession(): void
    {
        CacheManager::setDefaultConfig(new ConfigurationOption([
            'path' => $this->var_dir . '/sessions',
        ]));
        $session_store = CacheManager::getInstance('files');
        $GLOBALS["session_store"] = $session_store;
    }

    /**
     * @throws PhpfastcacheDriverNotFoundException
     * @throws PhpfastcacheInvalidConfigurationException
     * @throws PhpfastcacheExtensionNotInstalledException
     * @throws PhpfastcacheDriverCheckException
     * @throws PhpfastcacheInvalidTypeException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function setUpCaching(): void
    {
        CacheManager::setDefaultConfig(new ConfigurationOption([
            'path' => $this->var_dir . '/cache',
        ]));
        $cache_store = CacheManager::getInstance('files');
        $GLOBALS["caching"] = $cache_store;
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function setUpDatabase(): void
    {
        if(!empty($GLOBALS['stream_wrapper']['setting']) && $GLOBALS['stream_wrapper']['setting'] instanceof SettingStreamWrapper) {
            $database_settings = $this->setting_dir ."/database/database.yml";
            if (!file_exists($database_settings)) {
                $redirect = new RedirectResponse('/admin/configure/database');
                $redirect->send();
            }

            if ($this->installer_schema->environment === 'dev') {
                 $default_tables = Caching::init()->get('default.admin.built_in_tables');
                 if(\file_exists($default_tables)) {
                $tables_queries = Yaml::parseFile($default_tables)['table'] ?? [];
                try{
                    foreach($tables_queries as $query) {
                         $connection = Database::database()->con();
                         $statement = $connection->prepare($query);
                         $statement->execute();
                    }
                    $module_handler = ModuleHandler::factory();
                    $modules = $module_handler->getModules();
                    foreach($modules as $key=>$module) {
                        if ($module_handler->isModuleEnabled($key)) {
                            $module_install = $module['path'] . DIRECTORY_SEPARATOR . $key. '.install.php';
                            if (file_exists($module_install)) {
                                $database_install = $key . '_database_install';
                                require_once $module_install;
                                if (function_exists($database_install)) {
                                    $database_install();
                                }
                            }
                        }
                    }

                }catch(Throwable $e){
                    ErrorLogger::logger()->logError($e->getMessage()."\n".$e->getTraceAsString());
                }
            }
            }
        }
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function setUpProject(): void
    {
        $this->cacheDefaults();
    }

    protected function developerCustomRoutes(): array
    {
        $views_routes = $this->setting_dir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'views-routes.yml';
        $general_routes = $this->setting_dir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'general'.DIRECTORY_SEPARATOR.'general-routes.yml';

        if (!is_dir($this->setting_dir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'views')) {
            @mkdir($this->setting_dir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'views', 0777, true);
            @touch($views_routes);
        }
        if (!is_dir($this->setting_dir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'general')) {
            @mkdir($this->setting_dir . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'general', 0777, true);
            @touch($general_routes);
        }

        $routes = [];
        if (file_exists($views_routes)) {
            $routes = Yaml::parseFile($views_routes) ?? [];
        }
        if (file_exists($general_routes)) {
            $routes = array_merge($routes, Yaml::parseFile($general_routes) ?? []);
        }

        // Modules routes
        $module = ModuleHandler::factory();
        $modules_routes = $module->getModulesRoutes();
        $routes = \array_merge($routes, $modules_routes);
        return $routes;
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function cacheDefaults(): void
    {
        // Cache services
        $service_file = $this->setting_dir . DIRECTORY_SEPARATOR . 'defaults' . DIRECTORY_SEPARATOR . 'services'
            . DIRECTORY_SEPARATOR . 'default.services.yml';
        $services_custom = $this->setting_dir . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'custom.services.yml';
        $services = [];
        if (file_exists($service_file)) {
            $services = Yaml::parseFile($service_file) ?? [];
        }
        if (file_exists($services_custom)) {
            $services = array_merge($services, Yaml::parseFile($services_custom) ?? []);
        }

        foreach ($services as $key=>$service) {
            $services[$key] = base64_encode($service);
        }

        Caching::init()->set('system_services', $services);

        $setting_root = $this->setting_dir . DIRECTORY_SEPARATOR . 'defaults';
        $files = array_diff(scandir($setting_root) ?? [], ['..', '.']);
        $default_keys = [];

        foreach ($files as $file) {
            $full_path = $setting_root . DIRECTORY_SEPARATOR . $file;
            if (is_dir($full_path)) {
                $this->recursive_caching_defaults($full_path, $default_keys);
            }
        }

        $modules_templates = ModuleHandler::factory()->getModuleTemplates();
        foreach ($modules_templates as $template) {
            $this->recursive_caching_defaults($template, $default_keys);
        }

        Caching::init()->set('system.theme.keys', $default_keys);

        new ThemeManager();

        $default_route = Caching::init()->get('default.admin.routes');
        $routes = [];
        if (!empty($default_route) && file_exists($default_route)) {
            $routes = Yaml::parseFile($default_route);
        }

        if ($routes_custom = $this->developerCustomRoutes()) {
            $routes = array_merge($routes, $routes_custom);
        }

        foreach ($routes as $key=>$route) {
            $route = new Route($key, $route);
            Caching::init()->set($key, $route);
        }
        Caching::init()->set('system.routes.keys', array_keys($routes));
    }

    protected function recursive_caching_defaults(string $directory, &$keys): void
    {
        $list = array_diff(scandir($directory) ?? [], ['..', '.']);
        foreach ($list as $file) {
            $file_path = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_dir($file_path)) {
                $this->recursive_caching_defaults($file_path, $keys);
            }

            $list_name = explode('.', $file);
            $type = end($list_name) === 'twig' ? 'view' : 'admin';
            $list_n = array_slice($list_name, 0, -1);
            $key_name = "default.".$type.".". implode('.', $list_n);
            if (is_file($file_path)) {
                if ($type === 'view') {
                    $keys[] = $key_name;
                    $file_path = new TwigResolver($file_path);
                }
                Caching::init()->set($key_name, $file_path);
            }
        }
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    protected function recursive_theme_caching(string $directory, string $theme , &$keys): void
    {
        $list = array_diff(scandir($directory) ?? [], ['..', '.']);
        foreach ($list as $file) {
            $file_path = $directory . DIRECTORY_SEPARATOR . $file;
            $list_name = explode('.', $file);
            $type = end($list_name) === 'twig' ? 'view' : 'file';
            $list_n = array_slice($list_name, 0, -1);
            $key_name = "$theme.".$type.".". implode('.', $list_n);
            if (is_file($file_path)) {
                if ($type === 'view') {
                    $keys[] = $key_name;
                    $file_path = new TwigResolver($file_path);
                }
                Caching::init()->set($key_name, $file_path);
            }
        }
    }

    protected function recursive_mover(string $directory, string $directory_name): void
    {
        $settings = $this->setting_dir . DIRECTORY_SEPARATOR . 'defaults';
        if (!is_dir($settings)) {
            mkdir($settings);
        }
        $settings .= DIRECTORY_SEPARATOR . $directory_name;
        if (!is_dir($settings)) {
            mkdir($settings);
        }

        $list = array_diff(scandir($directory), ['..', '.']);
        foreach ($list as $file) {
            $file_full = $directory . DIRECTORY_SEPARATOR . $file;
            $new_file = $settings . DIRECTORY_SEPARATOR . $file;
            if (is_dir($file_full)) {
                $this->recursive_mover($file_full, $file);
            }
            else {
                @copy($file_full,$new_file);
            }
        }
    }
}
