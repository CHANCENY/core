<?php

namespace Simp\Core\modules\services;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;

/**
 * This class supports arbitrary dynamic properties via __get() and __set().
 */

class Service
{
    protected Container $container;
    protected array $arguments = [];
    public function __construct()
    {
        $this->container = DI_CONTAINER_SERVICES_TAGS;
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __get($name)
    {
        if ($this->container->has($name)) {
            return $this->container->get($name);
        }

        // try doing DI autowiring
        return $this->container->make($name,$this->arguments);
    }


    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __call(string $name, array $arguments)
    {
        $this->arguments = $arguments;
        return $this->__get($name);
    }

    /**
     * @param string $service_name
     * @param array $arguments
     * @return mixed
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function service(string $service_name, array $arguments = []): mixed
    {
        $this->arguments = $arguments;
        return $this->__get($service_name);
    }

    public static function serviceManager(): Service
    {
        return new self();
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public static function get(string $service_name, array $arguments = [])
    {
        $object = new static();
        return $object->service($service_name, $arguments);
    }
}