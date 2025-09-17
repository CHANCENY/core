<?php

namespace Simp\Core\extends\variables\src\Plugin;

use Simp\Core\lib\installation\SystemDirectory;
use Simp\Environment\Environment;
use Simp\Environment\Parser\Parser;
use Simp\Environment\Writer\Writer;

class Variables extends Environment
{

    public function __construct()
    {
    }

    private static function getStore(): string
    {
        $system = new SystemDirectory();
        $store_path = $system->root_dir . DIRECTORY_SEPARATOR . 'variables';
        if(!is_dir($store_path)) {
            mkdir($store_path, 0755, true);
        }

        return $store_path;
    }

    public static function getEditable(): Writer
    {
        return new Writer(self::getStore());
    }

    public static function getConfig(): Parser
    {
        return new Parser(self::getStore());
    }

    public static function create(string $key, $value): bool
    {
        $writer = self::getEditable();
        $key_hash = $writer->createStorage($key);
        return $writer->save($key_hash, $value);
    }

    public static function load(string $key): mixed
    {
        $reader = self::getConfig();
        return $reader->parse($key);
    }

}