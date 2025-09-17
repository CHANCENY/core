<?php

namespace Simp\Core\components\extensions;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\messager\Messager;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\views\ViewsManager;
use Symfony\Component\Yaml\Yaml;

class ModuleHandler extends SystemDirectory
{
    protected array $modules = [];

    protected string $default_module_dir = '';

    public function __construct()
    {
        parent::__construct();

        // Load the default modules. All default modules are in the extents directory.
        $default_modules = $this->webroot_dir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'modules';
        if (!is_dir($default_modules)) {
            @mkdir($default_modules, 0777, true);
        }

        $this->default_module_dir = $default_modules;
        $this->modules = array_diff(scandir($this->default_module_dir) ?? [], ['.', '..']);

        // Load the modules from the modules directory.
        $custom_modules = array_diff(scandir($this->module_dir) ?? [], ['.', '..']);
        $this->modules = array_merge($this->modules, $custom_modules);

        $unfiltered_modules = [];

        foreach ($this->modules as $module) {

            $module_file = $this->module_dir . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $module . '.info.yml';
            $default_file = $this->default_module_dir . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $module . '.info.yml';
            if (file_exists($module_file)) {
                $data = Yaml::parseFile($module_file) ?? [];
                $unfiltered_modules[$module] = [
                    'path' => $this->module_dir . DIRECTORY_SEPARATOR . $module,
                    ...$data,
                ];
            }

            elseif (file_exists($default_file)) {
                $data = Yaml::parseFile($default_file) ?? [];
                $unfiltered_modules[$module] = [
                    'path' => $this->default_module_dir . DIRECTORY_SEPARATOR . $module,
                    ...$data,
                ];
            }

        }

        $this->modules = $unfiltered_modules;

    }

    public function getModules(): array
    {
        return $this->modules;
    }

    public function getModule(string $module): array
    {
        return $this->modules[$module] ?? [];
    }

    public function getModulePath(string $module): string
    {
        return $this->modules[$module]['path'] ?? '';
    }

    /**
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function installModule(string $name): bool
    {
        $module = $this->modules[$name] ?? [];
        if (!isset($module['path'])) {
            return false;
        }

        $module_installer = $module['path'] . DIRECTORY_SEPARATOR . $name. '.install.php';
        if (file_exists($module_installer)) {
            include $module_installer;
        }

        // function that needs for installation are, route_install, database_install, content_type_install, views_install
        $database_install = $name . '_database_install';
        if (function_exists($database_install)) {
            if ($database_install()) {
                Messager::toast()->addMessage("Database tables created successfully");
            }
            else {
                Messager::toast()->addError("Database tables not created");
            }

        }

        $content_type_install = $name . '_content_type_install';
        if (function_exists($content_type_install)) {
            $content_types = $content_type_install();
            if (is_array($content_types) && $content_types !== []) {

                foreach ($content_types as $name=>$content_type) {
                    $content_type = ContentDefinitionManager::contentDefinitionManager()->getContentType($name);
                    if ($content_type === null || $content_type === []) {
                        ContentDefinitionManager::contentDefinitionManager()->addContentType($name, $content_type);
                    }
                }

                Messager::toast()->addMessage("Content types created successfully");

            }
        }

        $views_install = $name . '_views_install';
        if (function_exists($views_install)) {
            $views = $views_install();
            if (is_array($views) && $views !== []) {
                foreach ($views as $name=>$view) {
                    $view_old = ViewsManager::viewsManager()->getView($name);
                    if ($view_old === []) {
                        ViewsManager::viewsManager()->addView($name, $view);
                        $displays = $view['displays'] ?? [];
                        foreach ($displays as $display) {
                            $display_old = ViewsManager::viewsManager()->getDisplay($display);
                            if ($display_old === [] && !empty($views['display_settings'][$display])) {
                                ViewsManager::viewsManager()->addViewDisplay($name, $views['display_settings'][$display]);
                            }
                        }
                    }
                }

                Messager::toast()->addMessage("Views created successfully");
            }
        }

        return true;

    }

    public function getModulesRoutes(): array {

        $routes = [];
        foreach($this->modules as $name=>$module) {
             $module_installer = $module['path'] . DIRECTORY_SEPARATOR . $name. '.install.php';
            if (file_exists($module_installer) && $this->isModuleEnabled($name)) {
                 require_once $module_installer;
                  $route_install = $name . '_route_install';
                  if (\function_exists($route_install)) {
                    $routes = \array_merge($routes, $route_install());
                  }
            }
        }

        return $routes;
    }

    public function getModuleTemplates(): array
    {
        $templates = [];
        foreach($this->modules as $name=>$module) {
            $module_installer = $module['path'] . DIRECTORY_SEPARATOR . $name. '.install.php';
            if (file_exists($module_installer) && $this->isModuleEnabled($name)) {
                 require_once $module_installer;
                  $templates_install = $name . '_template_install';
                  if (\function_exists($templates_install)) {
                    $templates = \array_merge($templates, $templates_install());
                  }
            }
        }

        return \array_unique($templates);
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function moduleEnable(string $name): bool {
        $extension = $this->setting_dir. DIRECTORY_SEPARATOR . 'extension.yml';
        if (!file_exists($extension)) {
            touch($extension);
        }

        $extend = Yaml::parseFile($extension) ?? [];

        $extend[$name] = true;
        $this->installModule($name);
        return !in_array(\file_put_contents($extension, Yaml::dump($extend, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)), [0, false], true);
    }

    public static function factory(): ModuleHandler
    {
        return new ModuleHandler();
    }

    public function isModuleEnabled(string $name): bool {
        $extension = $this->setting_dir. DIRECTORY_SEPARATOR . 'extension.yml';
        if (!file_exists($extension)) {
            touch($extension);
        }

        $extend = Yaml::parseFile($extension) ?? [];
        return !empty($extend[$name]);
    }

    public function moduleDisable(mixed $name): bool
    {
        $extension = $this->setting_dir. DIRECTORY_SEPARATOR . 'extension.yml';
        if (!file_exists($extension)) {
            touch($extension);
        }

        $extend = Yaml::parseFile($extension) ?? [];

        $extend[$name] = false;
        return !in_array(\file_put_contents($extension, Yaml::dump($extend, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)), [0, false], true);
    }

    public function getFieldExtension(): array
    {
        $fields = [];
        foreach($this->modules as $name=>$module) {
            $module_installer = $module['path'] . DIRECTORY_SEPARATOR . $name. '.install.php';
            if (file_exists($module_installer) && $this->isModuleEnabled($name)) {
                require_once $module_installer;
                $field_install = $name . '_field_install';
                if (\function_exists($field_install)) {
                    $fields = \array_merge($fields, $field_install());
                }
            }
        }

        return \array_unique($fields);
    }

    public function attachLibrary(string $module, string $library_name): void
    {
        $module_installer = ($this->modules[$module]['path'] ?? '') . DIRECTORY_SEPARATOR . $module. '.install.php';

        if (file_exists($module_installer)) {
            require_once $module_installer;
            $library_install = $module . '_library_install';
            if (\function_exists($library_install)) {
                $assets =$library_install($library_name);

                foreach ($assets as $key=>$asset) {

                   foreach ($asset as $file) {
                       $extension = pathinfo((string) $file, PATHINFO_EXTENSION);
                       if ($key === 'head') {
                           if ($extension === 'css') {
                               $GLOBALS['theme']['head'][] = sprintf("<link rel='stylesheet' href='%s'>", $file);
                           }
                           elseif ($extension === 'js') {
                               $GLOBALS['theme']['head'][] = sprintf("<script src='%s'></script>", $file);
                           }
                       }

                       elseif ($key === 'footer') {

                           if ( $extension === 'css') {
                               $GLOBALS['theme']['footer'][] = sprintf("<link rel='stylesheet' href='%s'>", $file);
                           }
                           elseif ( $extension === 'js') {
                               $GLOBALS['theme']['footer'][] = sprintf("<script src='%s'></script>", $file);
                           }

                       }

                   }

                }
            }
        }

        $GLOBALS['theme']['head'] = array_unique($GLOBALS['theme']['head'] ?? []);
        $GLOBALS['theme']['footer'] = array_unique($GLOBALS['theme']['footer'] ?? []);
    }

    public function getConsoleCommands(): array
    {
        $commands = [];
        foreach($this->modules as $name=>$module) {
            $module_installer = $module['path'] . DIRECTORY_SEPARATOR . $name. '.install.php';
            if (file_exists($module_installer) && $this->isModuleEnabled($name)) {
                 require_once $module_installer;
                  $command_install = $name . '_command_install';
                  if (\function_exists($command_install)) {
                    $commands = \array_merge($commands, $command_install());
                  }
            }
        }

        return $commands;
    }

    public function getServicesProvider(): array
    {
        $services = [];
        foreach($this->modules as $name=>$module) {
            $module_installer = $module['path'] . DIRECTORY_SEPARATOR . $name. '.install.php';
            if (file_exists($module_installer) && $this->isModuleEnabled($name)) {
                 require_once $module_installer;
                  $service_install = $name . '_service_install';
                  if (\function_exists($service_install)) {
                    $services = \array_merge($services, $service_install());
                  }
            }
        }

        return $services;
    }

    public function getMiddlewares(): array
    {
        $middlewares = [];
        foreach($this->modules as $name=>$module) {
            $module_installer = $module['path'] . DIRECTORY_SEPARATOR . $name. '.install.php';
            if (file_exists($module_installer) && $this->isModuleEnabled($name)) {
                 require_once $module_installer;
                  $middleware_install = $name . '_middleware_install';
                  if (\function_exists($middleware_install)) {
                    $middlewares = \array_merge($middlewares, $middleware_install());
                  }
            }
        }

        return $middlewares;
    }

}
