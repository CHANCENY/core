<?php

namespace Simp\Core\modules\connection\interface;

interface ConnectionInterface
{
    public function select(string $query, array $params = []): array;

    public function insert(string $query, array $params = []): bool;

    public function update(string $query, array $params = []): bool;

    public function delete(string $query, array $params = []): bool;

    public function execute(string $query, array $params = []): mixed;

    public function tableExists(string $table): bool;

    public function createTable(string $query): bool;

    public function dropTable(string $table): bool;
}