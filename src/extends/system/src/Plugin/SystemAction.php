<?php

namespace Simp\Core\extends\system\src\Plugin;

use Simp\Core\lib\installation\InstallerValidator;
use Simp\Core\lib\installation\SystemDirectory;

class SystemAction
{
    public static function rebuildCore()
    {
        $system = new SystemDirectory();

        // Rebuild default installation configs
        $vendorDir = $system->root_dir . DIRECTORY_SEPARATOR . "vendor/simp/core/src/lib/installation/default_installation_configs";
        $srcDir    = $system->root_dir . DIRECTORY_SEPARATOR . "src/lib/installation/default_installation_configs";
        $destinationDefaults = $system->webroot_dir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . "defaults";

        $sourceDefaults = is_dir($vendorDir) ? $vendorDir : (is_dir($srcDir) ? $srcDir : null);
        if ($sourceDefaults) {
            if (!is_dir($destinationDefaults)) {
                mkdir($destinationDefaults, 0777, true);
            }
            self::recursiveCopy($sourceDefaults, $destinationDefaults);
        }

        // Rebuild assets
        $vendorAssets = $system->root_dir . DIRECTORY_SEPARATOR . "vendor/simp/core/src/modules/assets_manager/assets";
        $srcAssets    = $system->root_dir . DIRECTORY_SEPARATOR . "src/modules/assets_manager/assets";
        $assetsSource = is_dir($vendorAssets) ? $vendorAssets : (is_dir($srcAssets) ? $srcAssets : null);

        $destinationAssets = $system->webroot_dir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'assets';
        if ($assetsSource) {
            if (!is_dir($destinationAssets)) {
                mkdir($destinationAssets, 0777, true);
            }
            self::recursiveCopy($assetsSource, $destinationAssets);
        }
    }


    protected static function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0777, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $file;

            if (is_dir($srcPath)) {
                self::recursiveCopy($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }

        closedir($dir);
    }

    public static function moveModules(): void
    {
        $system = new SystemDirectory();
        $vendorDir = $system->root_dir . "/vendor/simp/core/src/extends";
        $srcDir    = $system->root_dir . "/src/extends";
        $destination = $system->webroot_dir.DIRECTORY_SEPARATOR ."core" . DIRECTORY_SEPARATOR . "modules";

        $source = is_dir($vendorDir) ? $vendorDir : (is_dir($srcDir) ? $srcDir : null);

        if (!$source) {
            return;
        }

        // Create a destination directory
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        // Delete everything in destination
        self::deleteRecursive($destination);

        // Copy from source to destination
        self::recursiveCopy($source, $destination);

    }

    protected static function deleteRecursive(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::deleteRecursive($path);
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }

    public static function rebuildCache(): void
    {
        $install = new InstallerValidator();
        $install->bootStorage();
    }

    public static function clearCache(): void
    {
        $system = new SystemDirectory();
        $cache_dir = $system->webroot_dir . DIRECTORY_SEPARATOR . "sites" . DIRECTORY_SEPARATOR . "var" . DIRECTORY_SEPARATOR . "cache";

        if (!is_dir($cache_dir)) {
            return; // nothing to clear
        }

        // Recursive function to delete files and folders
        $deleteFolder = function ($dir) use (&$deleteFolder) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path)) {
                    $deleteFolder($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($dir);
        };

        $deleteFolder($cache_dir);

        // Optionally, recreate the empty cache folder
        mkdir($cache_dir, 0755, true);
    }


    public static function copyInstallers()
    {
        $system = new SystemDirectory();
        $filesToCopy = [
            'install.php',
            'install_tasks.php',
            'site-config.php',
            'db-config.php',
            'mongodb-config.php',
            'rebuild.php',
            'InstallTasks.php'
        ];

        $destinationDir = $system->webroot_dir . DIRECTORY_SEPARATOR . 'core';

        // Determine source directory
        $vendorDir = $system->root_dir . DIRECTORY_SEPARATOR . "vendor/simp/core/src/lib/installation/installer";
        $srcDir    = $system->root_dir . DIRECTORY_SEPARATOR . "src/lib/installation/installer";
        $sourceDir = is_dir($vendorDir) ? $vendorDir : (is_dir($srcDir) ? $srcDir : null);

        if (!$sourceDir) {
            return; // no source found
        }

        // Ensure destination directory exists
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        foreach ($filesToCopy as $file) {
            $sourceFile = $sourceDir . DIRECTORY_SEPARATOR . $file;
            $destinationFile = $destinationDir . DIRECTORY_SEPARATOR . $file;

            // Delete existing file if it exists
            if (file_exists($destinationFile)) {
                unlink($destinationFile);
            }

            // Copy only if source file exists
            if (file_exists($sourceFile)) {
                copy($sourceFile, $destinationFile);
            }
        }
    }

    public static function rebuildAll(): void
    {
        self::copyInstallers();
        self::rebuildCore();
        self::moveModules();
        self::clearCache();
    }


}