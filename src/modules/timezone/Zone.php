<?php

namespace Simp\Core\modules\timezone;

class Zone
{
    public function __construct(
        protected string $tzCode,
        protected string $label,
        protected string $utc,
        protected string $name,
    )
    {
    }

    public function getTzCode(): string
    {
        return $this->tzCode;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUtc(): string
    {
        return $this->utc;
    }

    public function getName(): string
    {
        return $this->name;
    }

}