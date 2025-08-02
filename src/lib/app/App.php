<?php

namespace Simp\Core\lib\app;

use ReflectionClass;
use Exception;
use Phpfastcache\Drivers\Files\Driver;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverCheckException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheDriverNotFoundException;
use Phpfastcache\Exceptions\PhpfastcacheExtensionNotInstalledException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidConfigurationException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidTypeException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\auto_path\src\path\AutoPathAlias;
use Simp\Core\lib\installation\InstallerValidator;
use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\lib\memory\cache\Caching;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\config\ConfigManager;
use Simp\Core\modules\event_subscriber\EventSubscriber;
use Simp\Core\modules\logger\ErrorLogger;
use Simp\Core\modules\services\Service;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Simp\Router\Route as Router;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Throwable;


class App
{
    protected $currentRoute = null;
    /**
     * @throws PhpfastcacheDriverNotFoundException
     * @throws PhpfastcacheInvalidConfigurationException
     * @throws PhpfastcacheExtensionNotInstalledException
     * @throws PhpfastcacheDriverCheckException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheInvalidTypeException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws Exception
     */
    public function __construct()
    {

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->optionRequestHandler();
        }

        $response = new Response();
        $response = $this->mapRouteListeners();
    }

    public static function runApp(): App
    {
        return new App();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    protected function mapRouteListeners(): Response|JsonResponse|null
    {
        /**@var Driver $cache **/
        $cache = Caching::init()->driver();
        $route_keys = $cache->getItem('system.routes.keys');

        $system = new SystemDirectory;
      
        $middleware_file = $system->webroot_dir . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'defaults' . DIRECTORY_SEPARATOR .
            'middleware' . DIRECTORY_SEPARATOR . 'middleware.yml';

        // auto path
        $auto_path_alias = array();
        if(ModuleHandler::factory()->isModuleEnabled('auto_path')) {
            $auto_path_alias = AutoPathAlias::injectAliases();
        }

        $router = new Router($middleware_file);

        if ($route_keys->isHit()) {
            $route_keys = $route_keys->get();
            $response[] = null;

            foreach ($route_keys as $route_key) {
                $route = $cache->getItem($route_key);
                if ($route->isHit()) {
                    $route = $route->get();
                    $this->currentRoute = $route;
                    /**@var Route $route**/
                    // check methods
                    $methods = $route->method;
                    $path = $route->route_path;
                    $name = $route->controller_method;
                    $controller = $route->controller. "@" . $name;

                    $options = [
                        'access' => $route->access,
                        'route' => $route,
                        'key' => $route_key,
                    ];

                    if (count($methods) > 0) {
                        foreach ($methods as $method) {
                            $method_single = strtolower($method);
                            /**@var Response $response**/
                           $response[] = $router->$method_single($path, $name,$controller, $options);
                        }
                    }
                }

            }

            if (!empty($auto_path_alias)) {
                foreach ($auto_path_alias as $route_key => $route) {
                    /**@var Route $route**/
                    $this->currentRoute = $route;

                    // check methods
                    $methods = $route->method;
                    $path = $route->route_path;
                    $name = $route->controller_method;
                    $controller = $route->controller. "@" . $name;

                    $options = [
                        'access' => $route->access,
                        'route' => $route,
                        'key' => $route_key,
                    ];

                    if (count($methods) > 0) {
                        foreach ($methods as $method) {
                            $method_single = strtolower($method);
                            /**@var Response $response**/
                            $response[] = $router->$method_single($path, $name,$controller, $options);
                        }
                    }
                }
            }

            $response = array_filter($response);
            $response = reset($response);
            $response = $response ? $response : new Response("Page not found", 404);
            $response?->send(true);
        }
        else {

            $response = new Response("Page not found", 404);
            $response->send(true);
        }
        return $response;

    }

    /**
     * @throws PhpfastcacheExtensionNotInstalledException
     * @throws PhpfastcacheDriverCheckException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheInvalidTypeException
     * @throws PhpfastcacheDriverNotFoundException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheInvalidConfigurationException
     * @throws PhpfastcacheLogicException
     */
    public static function consoleApp(): void
    {
        $set_up_wizard = new InstallerValidator();
        $set_up_wizard->bootConsole();
    }

    protected function optionRequestHandler(): void
    {
        // Only handle OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if ($origin) {
            header("Access-Control-Allow-Origin: $origin");
            header("Vary: Origin");
        }

        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

        // Dynamically reflect back requested headers
        $requestedHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
        if ($requestedHeaders) {
            header("Access-Control-Allow-Headers: $requestedHeaders");
        }

        // Optional: Cache preflight for 1 day
       // header("Access-Control-Max-Age: 86400");

        http_response_code(201);
        exit();
    }


}