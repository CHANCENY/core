<?php

namespace Simp\Core\modules\pages;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\routes\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PageRouteController
{
    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __call(string $name, array $arguments)
   {
       extract($arguments);

       /**
        * @var Route $route
        */
       $route = $options['route'];

       $routeOptions = $route->getOptions();

       if (!file_exists($routeOptions['file'])) {
           return new Response("<h1>Page not found</h1>", 404);
       }

       if ($routeOptions['is_twig']) {
           return new Response(TemplateLoader::render($routeOptions['file']));
       }

       elseif ($routeOptions['is_php']) {

           $results = require_once $routeOptions['file'];

           if (is_array($results)) {
               return new JsonResponse($results);
           }

           return new Response($results);
       }

       return new Response("<h1> This page is not supported </h1>", 400);
   }
}