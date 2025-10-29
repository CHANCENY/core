<?php

namespace Simp\Core\lib\installation;

use JetBrains\PhpStorm\NoReturn;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\components\request\Request;
use Simp\Core\extends\variables\src\Plugin\Variables;
use Simp\Core\lib\app\App;
use Simp\Core\lib\file\file_system\stream_wrapper\GlobalStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\ModuleStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\PrivateStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\PublicStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\SettingStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\ThemeStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\VarStreamWrapper;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\routes\Route;
use Simp\Core\lib\themes\TwigResolver;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\pages\PageLoader;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\theme\ThemeManager;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Core\modules\user\roles\RolesRepository;
use Simp\StreamWrapper\WrapperRegister\WrapperRegister;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Yaml\Yaml;

class InstallerValidator extends SystemDirectory
{
    protected array $config;

    public function __construct()
    {
        parent::__construct();
        $GLOBALS['system_store'] = $this;
        $GLOBALS['request_start_time'] = microtime(true);
        // Inlined configuration from booter.yml
        $this->config = self::bootSettings($this);
    }

    public static function bootSettings(SystemDirectory $systemDirectory): array
    {
        return [
            'required_php_version' => '8.1',
            'required_extensions' => ['curl', 'mbstring', 'json'],
            'required_functions' => ['file_get_contents', 'curl_init'],
            'file_system_stream_wrappers' => [
                'global' => GlobalStreamWrapper::class,
                'public' => PublicStreamWrapper::class,
                'private' => PrivateStreamWrapper::class,
                'module' => ModuleStreamWrapper::class,
                'theme' => ThemeStreamWrapper::class,
                'setting' => SettingStreamWrapper::class,
                'var' => VarStreamWrapper::class,
            ],
            'writable_dirs' => ['global://', 'var://', 'public://', 'private://', 'setting://','module://', 'theme://', $systemDirectory->webroot_dir. DIRECTORY_SEPARATOR . 'core'],
        ];
    }

    public function validate(): array
    {
        $results = [];

        // Validate PHP version
        $results['php_version'] = version_compare(PHP_VERSION, $this->config['required_php_version'], '>=');

        // Validate required PHP extensions
        $results['extensions'] = [];
        foreach ($this->config['required_extensions'] as $ext) {
            $results['extensions'][$ext] = extension_loaded($ext);
        }

        // Validate required PHP functions
        $results['functions'] = [];
        foreach ($this->config['required_functions'] as $func) {
            $results['functions'][$func] = function_exists($func);
        }

        foreach ($this->config['file_system_stream_wrappers'] as $name => $class) {
            WrapperRegister::register($name, $class);
        }

        // Validate writable directories
        $results['writable_dirs'] = [];
        foreach ($this->config['writable_dirs'] as $dir) {
            if (!is_dir($dir)) {
                try {
                    mkdir($dir, 0777, true);
                } catch (\Throwable) {
                    $results['writable_dirs'][$dir] = false;
                    continue;
                }
            }

            $results['writable_dirs'][$dir] = is_writable($dir);
        }

        return $results;
    }

    public function isValid(): bool
    {
        $results = $this->validate();

        if (!$results['php_version']) {
            return false;
        }

        foreach (['extensions', 'functions', 'writable_dirs'] as $type) {
            foreach ($results[$type] as $ok) {
                if (!$ok) {
                    return false;
                }
            }
        }

        return true;
    }

    #[NoReturn] public function bootApplication(): void
    {

        if (!$this->isValid()) {
            echo "System validation failed. Cannot boot application.\n";
            exit(1);
        }

        new SystemDirectory();

        // Copy install.php to core directory
        $this->copyInstaller();


        // Prepare environment
        if (!$this->bootStorage()) {
            // Redirect to core/install.php
            $_SESSION['install'] = false;
            $redirect = "/core/db-config.php";;
            try{
                Database::database()->con();
                $redirect = \Symfony\Component\HttpFoundation\Request::createFromGlobals()->headers->get('referer');
                $_SESSION['install'] = true;
                $_SESSION['page_title'] = "Reboosting System Cache";
            }catch (\Throwable $e){
                if ($e->getCode() === 5555 || $e->getCode() === 6070) {
                    $_SESSION['install'] = true;
                    $_SESSION['page_title'] = "Installing Simple CMS";
                }
            }

            $url = '/core/install.php?dest='.$redirect;
            $response = new RedirectResponse($url);
            $response->send();
            exit;
        }
        else {
            $_SESSION['install'] = false;
            $this->bootVariables();
            App::runApp();
        }

    }

    public function bootConsole(): int
    {

        if (!$this->isValid()) {
            echo "System validation failed. Cannot boot application.\n";
            return 0;
        }

        new SystemDirectory();

        // Copy install.php to core directory
        $this->copyInstaller();
        $this->bootStorage();
        $this->bootVariables();
        return 0;
    }


    public function bootStorage(): bool
    {
        if (Caching::init()->get('system.booted')) {
            return true;
        }

        if (is_dir($this->webroot_dir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'defaults')) {
            $this->bootCache();
            Caching::init()->set('system.booted', true);
        }

        return false;
    }

    protected function bootCache()
    {
        $this->cacheDefaults();
    }

    protected function copyInstaller(): void
    {
        $filesToCopy = [
            'install.php',
            'install_tasks.php',
            'site-config.php',
            'db-config.php',
            'mongodb-config.php',
            'rebuild.php',
            'InstallTasks.php',
            'upload.php',
            'remove.php',
            'cron.php',
            'simp.php',
            'cron_executor.php',
            'console'
        ];

        $destinationDir = $this->webroot_dir . DIRECTORY_SEPARATOR . 'core';

        $flag = [];
        foreach ($filesToCopy as $file) {
            $flag[] = file_exists($destinationDir . DIRECTORY_SEPARATOR . $file);
        }

        if (in_array(true, $flag) && !in_array(false, $flag)) {
            return;
        }

        foreach ($filesToCopy as $file) {
            $source = __DIR__ . DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR . $file;
            $destination = $destinationDir . DIRECTORY_SEPARATOR . $file;

            if (!file_exists($source)) {
                continue;
            }

            if (!is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0777, true);
            }

            copy($source, $destination);
        }

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

        $pageLoader = Service::get(PageLoader::class);
        $pages = $pageLoader->getRoutes();

        // Modules routes
        $module = ModuleHandler::factory();
        $modules_routes = $module->getModulesRoutes();
        return \array_merge($routes, $modules_routes, $pages);
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function cacheDefaults(): void
    {
        $setting_root = $this->webroot_dir . DIRECTORY_SEPARATOR . 'core'.DIRECTORY_SEPARATOR .'defaults';
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

        $default_keys = [...$default_keys, ...$this->cacheCustomThemes()];

        Caching::init()->set('system.theme.keys', $default_keys);

        $default_route = Caching::init()->get('default.admin.routes');
        $routes = [];
        if (!empty($default_route) && file_exists($default_route)) {
            $routes = Yaml::parseFile($default_route);
        }

        if (($routes_custom = $this->developerCustomRoutes()) !== []) {
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
    protected function recursive_theme_caching(string $directory, string $theme, array &$keys): void
    {
        $list = array_diff(scandir($directory) ?: [], ['..', '.']);

        foreach ($list as $file) {
            $file_path = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_file($file_path)) {
                $list_name = explode('.', $file);
                $type = end($list_name) === 'twig' ? 'view' : 'file';
                $list_n = array_slice($list_name, 0, -1);
                $key_name = sprintf('%s.%s.', $theme, $type) . implode('.', $list_n);

                if ($type === 'view') {
                    $keys[] = $key_name;
                    $file_path = new TwigResolver($file_path);
                }

                Caching::init()->set($key_name, $file_path);
            }
            elseif (is_dir($file_path)) {
                // recurse deeper into theme directories
                $this->recursive_theme_caching($file_path, $theme, $keys);
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

    protected function bootVariables(): void
    {
        // ModuleHandler getModules installed
        $module_handler = ModuleHandler::factory();
        $module_handler->getModules();

        // Get module variables
        if (Database::database()->isTableExist('environment_variables')) {
            $query = Database::database()->con()->prepare("SELECT name FROM environment_variables");
            $query->execute();
            $variables = $query->fetchAll();
            $variables = array_column($variables, 'name');

            foreach ($variables as $variable) {
                if (!defined($variable)) {
                    define($variable, Variables::load($variable));
                }
            }
        }

        $custom_services = $module_handler->getServicesProvider();
        $defaults_services = [
            'theme.manager' => new ThemeManager(),
            'module.handler' => $module_handler,
            'system.directory' => new SystemDirectory(),
            'database' => Database::database(),
            'connection' => Database::database()->con(),
            'system.roles' => new RolesRepository($this),
            'current_user' => CurrentUser::currentUser(),
        ];

        $merged_services = array_merge($defaults_services, $custom_services);

        $container = new \DI\Container($merged_services);
        define('DI_CONTAINER_SERVICES_TAGS',$container);

        $dotenv = Dotenv\Dotenv::createImmutable($this->root_dir);
        $dotenv->load();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function cacheCustomThemes(): array
    {
        $keys = [];
        $files = array_diff(scandir($this->theme_dir ) ?? [], ['..', '.']);
        foreach ($files as $file) {
            $file_path = $this->theme_dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($file_path)) {
                $this->recursive_theme_caching($file_path, $file, $keys);
            }
        }

        return $keys;
    }

}
