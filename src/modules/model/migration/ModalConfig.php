<?php

namespace Simp\Core\modules\model\migration;

use DI\DependencyException;
use DI\NotFoundException;
use Simp\Core\modules\services\Service;

class ModalConfig
{
    public string $migration_path;

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __construct()
    {
        $this->migration_path = Service::get('system.directory')->root_dir . DIRECTORY_SEPARATOR . 'modal';

        if (!is_dir($this->migration_path)) {
            mkdir($this->migration_path, 0755, true);
        }
    }

    public static function path(): string
    {
        return (new static())->migration_path;
    }
}