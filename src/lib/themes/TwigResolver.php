<?php

namespace Simp\Core\lib\themes;

class TwigResolver implements \Stringable
{
    public function __construct(protected string $file_path) {}

    public function __toString(): string
    {
        return (string) (string) file_exists($this->file_path) !== '' && (string) file_exists($this->file_path) !== '0' ? file_get_contents($this->file_path) : '';
    }
}