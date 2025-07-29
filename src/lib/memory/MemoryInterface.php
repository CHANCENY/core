<?php

namespace Simp\Core\lib\memory;

interface MemoryInterface
{
    public function set(string $key, $value, int $duration = 3600): bool;

    public function get(string $key);

    public function has(string $key): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    public function deleteAll(array $keys): bool;

    public static function init();

    public function allKeys(): array;

}