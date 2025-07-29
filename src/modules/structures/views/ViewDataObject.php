<?php

namespace Simp\Core\modules\structures\views;

class ViewDataObject
{
    protected array $data = [];
    public function __construct(array $data)
    {
        foreach ($data as $key=>$row) {
            if (isset($this->data[$key])) {
                $this->data[$key] = array_merge($this->data[$key], $row[$key]);
            }else {
                $this->data[$key] = $row;
            }
        }
    }
    public function getData(): array {
        return $this->data;
    }

    public function get(string $field_name)
    {
        $found = $this->data[$field_name] ?? null;
        if (empty($found)) {
            return null;
        }

        if (is_array($found) && count($found) === 1) {
            return $found[0];
        }
        return $found;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->data[$name] ?? null;
    }

    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    public function __toString(): string
    {
        return json_encode($this->data);
    }
}