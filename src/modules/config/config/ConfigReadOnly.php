<?php

namespace Simp\Core\modules\config\config;

class ConfigReadOnly
{
    public function __construct(protected readonly string $name, protected readonly mixed $data)
    {
    }

    /**
     * @param string|null $name null if data being search is just string or number
     * @param mixed|null $default
     * @return mixed
     */
    public function get(?string $name = null, mixed $default = null): mixed
    {
        $config = [];
        if (empty($this->data)) {
            return $default;
        }

        foreach ($this->data as $key => $value) {
            if ($key === $name) {
                return $value;
            }
            elseif (is_array($value)) {
                $ok = $this->recursive_search($value, $name);
                if ($ok) {
                    return $ok;
                }
            }
        }
        return $default;
    }

    private function recursive_search(array $array, string $search)
    {
        foreach ($array as $key => $value) {
            if ($key === $search) {
                return $value;
            }
            if (is_array($value)) {
                return $this->recursive_search($value, $search);
            }
        }
        return null;
    }
}