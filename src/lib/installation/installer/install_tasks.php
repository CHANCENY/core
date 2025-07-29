<?php
// install_tasks.php
require_once __DIR__. '/../../vendor/autoload.php';

class InstallTasks
{
    public static function moveDirectories(): void
    {
        // Priority: use the vendor directory if it exists, else fallback to src
        $vendorDir = __DIR__ . "/../../vendor/simp/core/src/lib/installation/default_installation_configs";
        $srcDir    = __DIR__ . "/../../src/lib/installation/default_installation_configs";
        $destination = __DIR__ . DIRECTORY_SEPARATOR . "defaults";

        $source = is_dir($vendorDir) ? $vendorDir : (is_dir($srcDir) ? $srcDir : null);

        if (!$source) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        self::recursiveCopy($source, $destination);
        return;
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

    public static function moveModules()
    {
        $vendorDir = __DIR__ . "/../../vendor/simp/core/src/extends";
        $srcDir    = __DIR__ . "/../../src/extends";
        $destination = __DIR__ . DIRECTORY_SEPARATOR . "modules";

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

}

sleep(1); // Simulate delay

$action = $_POST['action'] ?? null;

switch ($action) {
    case 'directories':
       InstallTasks::moveDirectories();
        break;

    case 'modules':
        InstallTasks::moveModules();
        break;

    case 'finalize':
        // Finalize install (e.g., create lock file)
        break;

    default:
        http_response_code(400);
        echo "Invalid action.";
        exit;
}

echo "OK";
