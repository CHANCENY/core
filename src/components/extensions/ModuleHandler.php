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
            if (is_array($content_types) && count($content_types) > 0) {

                foreach ($content_types as $name=>$content_type) {
                    $content_type = ContentDefinitionManager::contentDefinitionManager()->getContentType($name);
                    if (empty($content_type)) {
                        ContentDefinitionManager::contentDefinitionManager()->addContentType($name, $content_type);
                    }
                }

                Messager::toast()->addMessage("Content types created successfully");

            }
        }

        $views_install = $name . '_views_install';
        if (function_exists($views_install)) {
            $views = $views_install();
            if (is_array($views) && count($views) > 0) {
                foreach ($views as $name=>$view) {
                    $view_old = ViewsManager::viewsManager()->getView($name);
                    if (empty($view_old)) {
                        ViewsManager::viewsManager()->addView($name, $view);
                        $displays = $view['displays'] ?? [];
                        foreach ($displays as $display) {
                            $display_old = ViewsManager::viewsManager()->getDisplay($display);
                            if (empty($display_old)) {
                                if (!empty($views['display_settings'][$display])) {
                                    ViewsManager::viewsManager()->addViewDisplay($name, $views['display_settings'][$display]);
                                }
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

        $routes = array();
        foreach($this->modules as $name=>$module) {
             $module_installer = $module['path'] . DIRECTORY_SEPARATOR . $name. '.install.php';
            if (file_exists($module_installer) && $module['enabled'] === true) {
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
        $templates = array();
        foreach($this->modules as $name=>$module) {
            $module_installer = $module['path'] . DIRECTORY_SEPARATOR . $name. '.install.php';
            if (file_exists($module_installer) && $module['enabled'] === true) {
                 require_once $module_installer;
                  $templates_install = $name . '_template_install';
                  if (\function_exists($templates_install)) {
                    $templates = \array_merge($templates, $templates_install());
                  }
            }
        }
        return \array_unique($templates);
    }

    public function moduleEnable(string $name): bool {
        $module = $this->modules[$name] ?? [];
        if (!empty($module)) {
            $module['enabled'] = true;
            $path = $module['path']. \DIRECTORY_SEPARATOR . $name . '.info.yml';
            unset($module['path']);
            if (\file_exists($path)) {
                return !empty(\file_put_contents($path, Yaml::dump($module, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)));
            }
        }
        return false;
    }

    public static function factory(): ModuleHandler
    {
        return new ModuleHandler();
    }

    public function isModuleEnabled(string $name): bool {
        $module = $this->modules[$name] ?? [];
        if (!empty($module)) {
            return $module['enabled'] ?? false;
        }
        return false;
    }

    public function moduleDisable(mixed $name): bool
    {
        $module = $this->modules[$name] ?? [];
        if (!empty($module)) {
            unset($module['enabled']);
            $path = $module['path']. \DIRECTORY_SEPARATOR . $name . '.info.yml';
            unset($module['path']);
            if (\file_exists($path)) {
                return !empty(\file_put_contents($path, Yaml::dump($module, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)));
            }
        }
        return false;
    }

    public function getFieldExtension(): array
    {
        $fields = array();
        foreach($this->modules as $name=>$module) {
            $module_installer = $module['path'] . DIRECTORY_SEPARATOR . $name. '.install.php';
            if (file_exists($module_installer) && $module['enabled'] === true) {
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

                       if ($key === 'head') {

                           if (str_starts_with($file, '/core/extends')) {
                               $file = $this->root_dir . DIRECTORY_SEPARATOR . $file;

                               $extension = pathinfo($file, PATHINFO_EXTENSION);
                               if (file_exists($file) && $extension === 'css') {
                                   $content = file_get_contents($file);
                                   $GLOBALS['theme']['head'][] = "<style>{$content}</style>\n";
                               }
                               elseif (file_exists($file) && $extension === 'js') {
                                   $content = file_get_contents($file);
                                   $GLOBALS['theme']['head'][] = "<script>{$content}</script>\n";
                               }
                           }
                           elseif (str_starts_with($file, '/module')) {
                               $file = $this->module_dir . $file;
                               $extension = pathinfo($file, PATHINFO_EXTENSION);
                               if (file_exists($file) && $extension === 'css') {
                                   $GLOBALS['theme']['head'][] = "link rel='stylesheet' href='{$file}'\n";
                               }
                               elseif (file_exists($file) && $extension === 'js') {
                                   $GLOBALS['theme']['head'][] = "<script src='{$file}'></script>\n";
                               }
                           }

                       }

                       elseif ($key === 'footer') {

                           if (str_starts_with($file, '/core/extends')) {
                               $file = $this->root_dir . DIRECTORY_SEPARATOR . $file;
                               $extension = pathinfo($file, PATHINFO_EXTENSION);
                               if (file_exists($file) && $extension === 'css') {
                                   $content = file_get_contents($file);
                                   $GLOBALS['theme']['footer'][] = "<style>{$content}</style>\n";
                               }
                               elseif (file_exists($file) && $extension === 'js') {
                                   $content = file_get_contents($file);
                                   $GLOBALS['theme']['footer'][] = "<script>{$content}</script>\n";
                               }
                           }
                           elseif (str_starts_with($file, '/module')) {
                               $file = $this->module_dir . $file;
                               $extension = pathinfo($file, PATHINFO_EXTENSION);
                               if (file_exists($file) && $extension === 'css') {
                                   $GLOBALS['theme']['footer'][] = "link rel='stylesheet' href='{$file}'\n";
                               }
                               elseif (file_exists($file) && $extension === 'js') {
                                   $GLOBALS['theme']['footer'][] = "<script src='{$file}'></script>\n";
                               }
                           }

                       }

                   }

                }
            }
        }

    }

}
