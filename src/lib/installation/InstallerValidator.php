<?php

namespace Simp\Core\lib\installation;

use JetBrains\PhpStorm\NoReturn;
use Simp\Core\lib\file\file_system\stream_wrapper\GlobalStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\ModuleStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\PrivateStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\PublicStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\SettingStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\ThemeStreamWrapper;
use Simp\Core\lib\file\file_system\stream_wrapper\VarStreamWrapper;
use Simp\StreamWrapper\WrapperRegister\WrapperRegister;
use Symfony\Component\HttpFoundation\RedirectResponse;

class InstallerValidator extends SystemDirectory
{
    protected array $config;

    public function __construct()
    {
        parent::__construct();
        $GLOBALS['system_store'] = $this;
        $GLOBALS['request_start_time'] = microtime(true);
        // Inlined configuration from booter.yml
        $this->config = [
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
            'writable_dirs' => ['global://', 'var://', 'public://', 'private://', 'setting://','module://', 'theme://', $this->webroot_dir. DIRECTORY_SEPARATOR . 'core'],
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
                } catch (\Throwable $e) {
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

        if (!$results['php_version']) return false;

        foreach (['extensions', 'functions', 'writable_dirs'] as $type) {
            foreach ($results[$type] as $ok) {
                if (!$ok) return false;
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

        // Prepare environment
        $this->bootStorage();
        $this->bootCache();

        // Copy install.php to core directory
        $this->copyInstaller();

        // Redirect to core/install.php
        $url = '/core/install.php';
        $response = new RedirectResponse($url);
        $response->send();

        // Optional: exit to prevent further execution
        exit;
    }


    protected function bootStorage()
    {
        echo "Storage directory prepared.\n";
    }

    protected function bootCache()
    {
        echo "Cache initialized.\n";
    }

    protected function copyInstaller(): void
    {
        $filesToCopy = [
            'install.php',
            'install_tasks.php',
            'site-config.php'
        ];

        $destinationDir = $this->webroot_dir . DIRECTORY_SEPARATOR . 'core';

        foreach ($filesToCopy as $file) {
            $source = __DIR__ . DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR . $file;
            $destination = $destinationDir . DIRECTORY_SEPARATOR . $file;

            if (!file_exists($source)) {
                echo "Source file not found: $source\n";
                continue;
            }

            if (!is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0777, true);
            }

            if (!copy($source, $destination)) {
                echo "❌ Failed to copy $file to core directory.\n";
            } else {
                echo "✅ $file copied to core directory.\n";
            }
        }
    }


}
