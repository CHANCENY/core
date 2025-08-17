<?php

namespace Simp\Core\lib\routes;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use ReflectionClass;
use ReflectionException;
use Simp\Core\lib\memory\cache\Caching;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Simp\Core\modules\services\Service;

class Route
{

    public string $route_title;
    public string $route_path;
    public array $method = [];
    public string $controller;
    public string $controller_method;
    public array $access;
    public string $route_type;
    public array $options;

    public function __construct(public readonly string $route_id, private readonly array $route_data)
    {
        $this->route_title = $route_data['title'];
        $this->route_path = $route_data['path'];
        $this->method = $route_data['method'];
        $this->controller = $route_data['controller']['class'];
        $this->controller_method = $route_data['controller']['method'];
        $this->access = $route_data['access'] ?? [];
        $this->route_type = $route_data['route_type'] ?? 'default';
        $this->options = $route_data['options'] ?? [];
    }

    public static function getCurrentRoute(): Route|array
    {
        $request = \Simp\Core\components\request\Request::createFromGlobals();
        return $request->server->get('ROUTE_ATTRIBUTES')['route'] ?? [];

    }

    public function getRouteId(): string
    {
        return $this->route_id;
    }

    public function getRouteTitle(): string
    {
        return $this->route_title;
    }

    public function getRoutePath(): string
    {
        return $this->route_path;
    }

    public function getMethod(): array
    {
        return $this->method;
    }

    public function getController(): string
    {
        return $this->controller;
    }

    public function getControllerMethod(): string
    {
        return $this->controller_method;
    }

    public function getAccess(): array
    {
        return $this->access;
    }

    public function __get(string $name)
    {
        return match ($name) {
            'access' => $this->access,
            'controller', 'class' => $this->controller,
            'controller_method' => $this->controller_method,
            'method', 'methods' => $this->method,
            'route_id', 'id' => $this->route_id,
            'route_title', 'title' => $this->route_title,
            'route_path', 'path' => $this->route_path,
            'options' => $this->options,
            default => null,
        };
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function fromRouteUrl(string $url): ?Route
    {
        $routes = Caching::init()->get('system.routes.keys');
        $found = array_filter( $routes, function ($item) use ($url) {
            $route = Caching::init()?->get($item);
            return $route?->route_path === $url ? $route : null;
        });
        $key = reset($found);
        return $key ? Caching::init()?->get($key) : null;
    }

    /**
     * @param Route $route
     * @param array $controller_method_arguments
     * @return Response|JsonResponse|RedirectResponse
     * @throws ReflectionException
     *
     */
    public static function getControllerResponse(Route $route, array $controller_method_arguments = []): Response|JsonResponse|RedirectResponse
    {
        $controller = new ReflectionClass($route->getController());
        $controller = $controller->newInstance();
        $method = $route->getControllerMethod();
        $options = [
            'request'=>Service::serviceManager()->request,
            'route_name' => $route->route_id,
            'options' => $controller_method_arguments

        ];
        return $controller->$method(...$options);
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function fromRouteName(string $route_name): ?Route
    {
        $route = Caching::init()?->get($route_name);
        if ($route) {
            return $route;
        }
        return null;
    }

    public function getRouteType(): string
    {
        return $this->route_type;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function toArray()
    {
        return $this->route_data;
    }

    public static function url(string $route_name, array $options = [], array $params = []): ?string
    {
        /**
         * @throws PhpfastcacheCoreException
         * @throws PhpfastcacheLogicException
         * @throws PhpfastcacheDriverException
         * @throws PhpfastcacheInvalidArgumentException
         */
        $builder = function(string $id, array $options, array $params = []): ?string
        {
            if (!empty($options['nid']) && empty($options['is_alias'])) {

                $alias = AutoPathAlias::createRouteId($options['nid']);
                $options['is_alias'] = true;
                $found = url($alias, $options, $params);
                if (!empty($found)) {
                    return $found;
                }
            }

            if (!empty($id)) {
                $route = Caching::init()->get($id);
                if (empty($route) && ModuleHandler::factory()->isModuleEnabled('auto_path')) {
                    $routes = AutoPathAlias::injectAliases();
                    $route = $routes[$id] ?? null;
                }
                if ($route instanceof Route) {
                    $pattern = $route->getRoutePath();
                    $generatePath = function (string $pattern, array $values): string {
                        return getStr($pattern, $values);
                    };
                    $with_value_pattern = $generatePath($pattern, $options);


                    return empty($params) ? $with_value_pattern : $with_value_pattern . '?'. http_build_query($params);
                }
            }
            return null;
        };
        return $builder($route_name, $options, $params);
    }

}