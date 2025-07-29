<?php

namespace Simp\Core\components\rest_data_source\interface;

use Simp\Core\lib\routes\Route;

interface RestDataSourceInterface
{
    public function __construct(Route $route, array $options);
    public function getResponse(): array;
    public function getStatusCode(): int;
}