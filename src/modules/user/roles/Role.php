<?php

namespace Simp\Core\modules\user\roles;

use Simp\Core\modules\database\Database;

class Role
{
    public function __construct(
                                protected int $rid,
                                protected string $name,
                                protected int $uid,
                                protected string $role_name,
                                protected string $role_label,
    ){}

    public function getRid(): int
    {
        return $this->rid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getRoleName(): string
    {
        return $this->role_name;
    }

    public function getRoleLabel(): string
    {
        return $this->role_label;
    }

    public function delete(): bool
    {
        $query = "DELETE FROM `user_roles` WHERE `rid` = :rid AND `uid` = :uid";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':rid', $this->rid);
        $query->bindParam(':uid', $this->uid);
        return $query->execute();
    }

    public function __toString(): string
    {
        return $this->role_name;
    }
}