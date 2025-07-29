<?php

namespace Simp\Core\lib\themes;

class TwigResolver
{
    public function __construct(protected string $file_path) {}

    public function __toString(): string
    {
        return file_exists($this->file_path) ? file_get_contents($this->file_path) : '';
    }
}