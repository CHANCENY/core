<?php

namespace Simp\Core\components\rest_data_source;

use Simp\Core\components\rest_data_source\interface\RestDataSourceInterface;
use Simp\Core\lib\routes\Route;

 class RestDataSource implements RestDataSourceInterface
{

    protected array $results = ['Api route is active'];

    protected int $status = 200;

    public function __construct(Route $route, array $options)
    {
    }

    public function getResponse(): array
    {
       return $this->results;
    }

     public function getStatusCode(): int
     {
         return $this->status;
     }
 }