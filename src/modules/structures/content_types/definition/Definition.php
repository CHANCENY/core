<?php

namespace Simp\Core\modules\structures\content_types\definition;

class Definition
{
    protected array $storage;
    public function __construct(protected string $title, protected string $description, protected string $machine_name, ...$storage)
    {
        $this->storage = $storage;
    }
    public function getTitle(): string
    {
        return $this->title;
    }
    public function getDescription(): string
    {
        return $this->description;
    }
    public function getMachineName(): string
    {
        return $this->machine_name;
    }
    public function getFields()
    {
        return $this->storage['fields'] ?? [];
    }

}