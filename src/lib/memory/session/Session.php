<?php

namespace Simp\Core\lib\memory\session;

use Phpfastcache\Drivers\Files\Driver;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Phpfastcache\Exceptions\PhpfastcacheUnsupportedMethodException;
use Psr\Cache\InvalidArgumentException;
use Simp\Core\lib\memory\MemoryInterface;

class Session implements MemoryInterface
{

    protected Driver|array $session_object;

    protected string $session_id;

    public function __construct()
    {
        $this->session_object = $GLOBALS["session_store"];
        $this->session_id = session_id();
    }

    private function persistAndReload(): bool
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            $_SESSION = $this->session_object;
            $this->session_object = $_SESSION;
            return true;
        }
        return false;
    }

    /**
     * @param string $key
     * @param $value
     * @param int $duration
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function set(string $key, $value, int $duration = 525600): bool
    {
        $key = $key.".". $this->session_id;
       if ($this->session_object instanceof Driver) {
           $instance = $this->session_object->getItem($key);
           $instance->set($value)->expiresAfter($duration);
           return $this->session_object->save($instance);
       }
       $this->session_object[$key] = $value;
       return $this->persistAndReload();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get(string $key)
    {
        $key = $key.".". $this->session_id;
        if ($this->session_object instanceof Driver) {
            $instance = $this->session_object->getItem($key);
            if ($instance->isHit()) {
                return $instance->get();
            }
            return null;
        }
        return $this->session_object[$key] ?? null;
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function has(string $key): bool
    {
        $key = $key.".". $this->session_id;
        if ($this->session_object instanceof Driver) {
            $instance = $this->session_object->getItem($key);
            return $instance->isHit();
        }
        return !empty($this->session_object[$key]);
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete(string $key): bool
    {
        $key = $key.".". $this->session_id;
       if ($this->session_object instanceof Driver) {
           return $this->session_object->deleteItem($key);
       }

       if (empty($this->session_object[$key])) {
           return true;
       }
       unset($this->session_object[$key]);
       return $this->persistAndReload();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     */
    public function clear(): bool
    {
        if ($this->session_object instanceof Driver) {
            return $this->session_object->clear();
        }
        $this->session_object = [];
        return $this->persistAndReload();
    }

    /**
     * @throws InvalidArgumentException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function deleteAll(array $keys): bool
    {
        if ($this->session_object instanceof Driver) {
            return $this->session_object->deleteItems($keys);
        }
        foreach ($keys as $key) {
            $key = $key.".". $this->session_id;
            $this->delete($key);
        }
        return $this->persistAndReload();
    }

    public static function init(): Session
    {
        return new self();
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheUnsupportedMethodException
     */
    public function allKeys(): array
    {
        if ($this->session_object instanceof Driver) {
            return iterator_to_array($this->session_object->getAllItems());
        }
        return array_keys($this->session_object);
    }
}