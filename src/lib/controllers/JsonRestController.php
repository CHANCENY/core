<?php

namespace Simp\Core\lib\controllers;

use Exception;
use Throwable;
use Simp\Core\components\rest_data_source\interface\RestDataSourceInterface;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\integration\rest\JsonRestManager;
use Simp\Core\modules\logger\ErrorLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class JsonRestController
{
    public function handle_api_request(...$args): JsonResponse
    {
        extract($args);

        /**@var $route Route */
        $route = $args['options']['route'] ?? null;

        /**@var $request Request**/
        if ($route !== null) {
            try {
                $handler = JsonRestManager::factory()->getVersionRouteDataSourceSetting($route->route_id);
                $handler = new $handler($route, $args);
                if ($handler instanceof RestDataSourceInterface) {

                    $response = new JsonResponse($handler->getResponse(), $handler->getStatusCode());

                    $response->headers->set('Access-Control-Allow-Origin',  $origin = $_SERVER['HTTP_ORIGIN'] ?? null);
                    $response->headers->set('Vary', 'Origin');
                    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');

                    // If the request had custom headers, reflect them back:
                    if (!empty($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                        $response->headers->set('Access-Control-Allow-Headers', $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
                    }

                    // Optional: support credentials
                    $response->headers->set('Access-Control-Allow-Credentials', 'true');

                    return $response;
                }
                throw new Exception('Handler not found  or handle has not implemented RestDataSourceInterface');
            }catch (Throwable $exception) {
                ErrorLogger::logger()->logError($exception);
            }

            return new JsonResponse(['status'=>true]);
        }
        return new JsonResponse(['error' => 'Endpoint not found']);
    }
}