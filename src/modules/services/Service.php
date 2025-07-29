<?php

namespace Simp\Core\modules\services;

use ReflectionClass;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use ReflectionException;
use Simp\Core\components\request\Request;
use Simp\Core\lib\memory\cache\Caching;

/**
 * This class supports arbitrary dynamic properties via __get() and __set().
 */

/**
 * @property mixed $anyProperty
 * @property Request|null $request
 */
class Service
{
    protected array $services = [];

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct()
    {
        $services = Caching::init()->get('system_services') ?? [];
        if (!empty($services)) {
            $this->services = $services;
        }
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws ReflectionException
     */
    public function __get($name)
    {
        $service = $this->services[$name] ?? null;
        if ($service) {
            $service = base64_decode($service);
            $reflection = new ReflectionClass($service);
            if (!$reflection->getConstructor()) {
                return $reflection->newInstance();
            }

            $create_function = $service.'::create';
            if (function_exists($create_function)) {
                $parameters = $create_function();
                return $reflection->newInstanceArgs($parameters);
            }
            return $reflection->newInstance();
        }
        return null;

    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws ReflectionException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __call(string $name, array $arguments)
    {
        return $this->__get($name);
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws ReflectionException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function service(string $service_name)
    {
        return $this->__get($service_name);
    }

    public static function serviceManager(): Service
    {
        return new self();
    }
}