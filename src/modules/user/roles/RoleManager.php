<?php

namespace Simp\Core\modules\user\roles;

use PDO;
use Simp\Core\modules\database\Database;

class RoleManager
{
    public function __construct(int $uid)
    {
        $query = Database::database()->con()->prepare("SELECT `name` FROM `user_roles` WHERE `uid` = :uid");
        $query->bindValue(':uid', $uid);
        $query->execute();
        $roles = $query->fetchAll(PDO::FETCH_CLASS, Role::class);
        dump($roles);

    }
}