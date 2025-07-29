<?php

namespace Simp\Core\modules\files\helpers;

use Simp\Core\lib\file\file_system\stream_wrapper\GlobalStreamWrapper;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\files\entity\File;
use Symfony\Component\Yaml\Yaml;

class FileFunction
{
    public static function reserve_uri(string $uri, bool $webroot = true): string
    {
        $system = new SystemDirectory();
        $schema = $system->schema_dir . DIRECTORY_SEPARATOR. "booter.yml";
        if (file_exists($schema)) {
            $schema = Yaml::parseFile($schema, Yaml::PARSE_OBJECT_FOR_MAP);
            $stream = $schema->streams ?? [];
            foreach ($stream as $file) {
                $file = (array)$file;
                $stream_name = key($file);
                /**@var GlobalStreamWrapper $stream_object**/
                $stream_object = new $file[$stream_name]();
                if (str_starts_with($uri, $stream_name)) {
                    $new_line = str_replace("$stream_name:/", $stream_object->getbase_path(), $uri);

                    if ($webroot) {
                        $webroot = $system->webroot_dir;
                        $new_line = substr($new_line,strlen($webroot),strlen($new_line));
                    }
                    return $new_line;
                }
            }
        }
        return $uri;
    }

    public static function resolve_fid(?int $fid): string
    {
        if (empty($fid)) {
            return '';
        }

        $file = File::load($fid);
        if ($file instanceof File) {
            return FileFunction::reserve_uri($file->getUri());
        }
        return '';
    }

    public static function file(int $fid): ?File
    {
        return File::load($fid);
    }
}