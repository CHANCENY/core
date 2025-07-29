<?php

namespace Simp\Core\modules\assets_manager;

use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\lib\memory\cache\Caching;

class AssetsManager
{
    public function getAssetsFile(string $filename, bool $content = true): string
    {
        $files = array_diff(scandir(__DIR__.DIRECTORY_SEPARATOR.'assets') ?? [], ['..', '.']);
        foreach ($files as $file) {
            $full_path = __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$file;
            if (file_exists($full_path) && $file === $filename) {
                return $content ? file_get_contents($full_path) : $full_path;
            }
            elseif (is_dir($full_path)) {
                $found = $this->recursive_read($full_path ,$filename, $content);
                if (!empty($found)) {
                    return $found;
                }
            }
        }
        return '';
    }

    private function recursive_read(string $path, string $filename, bool $content): string
    {
        $files = array_diff(scandir($path) ?? [], ['..', '.']);
        foreach ($files as $file) {
           $full_path = $path.DIRECTORY_SEPARATOR.$file;
           if (file_exists($full_path) && $file === $filename) {
               return $content ? file_get_contents($full_path) : $full_path;
           }
           elseif (is_dir($full_path)) {
               $found = $this->recursive_read($full_path ,$filename, $content);
               if (!empty($found)) {
                   return $found;
               }
           }
        }
        return '';
    }

    public function attach_library(string $library_name)
    {
        $module_handler = ModuleHandler::factory();
        $modules = $module_handler->getModules();

        foreach ($modules as $key=>$module) {

            if (!empty($module['enabled'])) {
                $module_install = $module['path'] . DIRECTORY_SEPARATOR . $key.'.install.php';
                if (file_exists($module_install)) {
                    require_once $module_install;
                    $library_install = $key . '_library_install';
                    if (function_exists($library_install)) {
                        $libraries = $library_install();
                        if (!empty($libraries) && $libraries[$library_name]) {
                            $file = $libraries[$library_name];

                            foreach ($file as $file) {

                                if (str_starts_with($file, '/core/extends')) {
                                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                                }
                            }
                        }
                    }
                }
            }

        }

    }

    public function adminHeadAssets(): string
    {
        return "default.view.admin_head_assets";
    }

    public function adminFooterAssets(): string
    {
        return "default.view.admin_footer_assets";
    }

    public function adminNavigation(): string
    {
        return "default.view.admin_navigation";
    }

    public static function assetManager(): AssetsManager
    {
        return new self();
    }

}