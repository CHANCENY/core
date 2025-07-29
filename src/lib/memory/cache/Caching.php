<?php

namespace Simp\Core\lib\memory\cache;

use Phpfastcache\Drivers\Files\Driver;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Psr\Cache\InvalidArgumentException;
use Simp\Core\lib\memory\MemoryInterface;

class Caching implements MemoryInterface
{
    /**
     * @var Driver|mixed
     */
    protected Driver $caching_object;

    public function __construct()
    {
        $this->caching_object = $GLOBALS["caching"] ?? '';
    }

    /**
     * Save data to cache.
     * @param string $key
     * @param $value
     * @param int $duration
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function set(string $key, $value, int $duration = 525600): bool
    {
        $instance = $this->caching_object->getItem($key);
        $instance->set($value)->expiresAfter($duration);
        return $this->caching_object->save($instance);
    }

    /**
     * Get Item from cache.
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get(string $key)
    {
        $instance = $this->caching_object->getItem($key);
        return $instance->get();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function has(string $key): bool
    {
        $instance = $this->caching_object->getItem($key);
        return $instance->isHit();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete(string $key): bool
    {
        return $this->caching_object->deleteItem($key);
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     */
    public function clear(): bool
    {
        return $this->caching_object->clear();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws InvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function deleteAll(array $keys): bool
    {
        return $this->caching_object->deleteItems($keys);
    }

    public static function init(): Caching
    {
        return new self();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function allKeys(): array
    {
        return iterator_to_array($this->caching_object->getItems());
    }

    public function rebuild(): void
    {
        $command = "usr/bin/php ". getcwd() . "/vendor/bin/simp.php cache:clear";
        exec($command, $output, $return);
    }
}