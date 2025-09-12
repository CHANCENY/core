<?php

namespace Simp\Core\modules\services;

use PDO;
use Simp\Core\modules\database\Database;

class Sample
{
    private string $name = "Sample";

    public function __construct(PDO $connection)
    {
    }
}