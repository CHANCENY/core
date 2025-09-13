<?php

namespace Simp\Core\modules\model\model;

use DI\DependencyException;
use DI\NotFoundException;
use PDO;
use Simp\Core\modules\services\Service;
use Simp\Modal\Modal\Modal;

class Model extends Modal
{
    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __construct(?PDO $pdo = null)
    {
        if ($pdo === null) {
            $pdo = Service::get('connection');
        }
        parent::__construct($pdo);
    }
}