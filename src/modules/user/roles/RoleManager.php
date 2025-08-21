<?php

namespace Simp\Core\modules\user\roles;

use PDO;
use Simp\Core\modules\database\Database;

class RoleManager
{
    protected array $roles = [];
    public function __construct(protected int $uid)
    {
        $query = Database::database()->con()->prepare("SELECT * FROM `user_roles` WHERE `uid` = :uid");
        $query->bindValue(':uid', $this->uid);
        $query->execute();
        $roles = $query->fetchAll(PDO::FETCH_ASSOC);
        $this->roles = array_map(fn($role) => new Role(...$role), $roles);

        if ($this->uid === 0) {
            $this->roles[] = new Role(0, 'anonymous', 0,'anonymous', 'anonymous');
        }
    }

    public function appendRole(string $name): RoleManager|bool
    {
        $query = Database::database()->con()->prepare("INSERT INTO `user_roles` (`uid`, `role_name`, `role_label`, `name`) VALUES (:uid, :role_name, :role_label, :name)");
        $query->bindValue('name', $name);
        $query->bindValue('role_name', $name);
        $query->bindValue('role_label', $name);
        $query->bindValue(':uid', $this->uid);
        $query->execute();
        return new RoleManager($this->uid);
    }

    public function delete(string $name): RoleManager
    {
        $query = Database::database()->con()->prepare("DELETE FROM `user_roles` WHERE `uid` = :uid AND `role_name` = :role_name");
        $query->bindValue(':uid', $this->uid);
        $query->bindValue(':role_name', $name);
        $query->execute();
        return new RoleManager($this->uid);
    }

    public function deleteAll(): RoleManager
    {
        $query = Database::database()->con()->prepare("DELETE FROM `user_roles` WHERE `uid` = :uid");
        $query->bindValue(':uid', $this->uid);
        $query->execute();
        return new RoleManager($this->uid);
    }

    public function isRoleExist(string $name): bool
    {
        foreach ($this->roles as $role) {
            if ($role->getName() === $name) {
                return true;
            }
        }
        return false;
    }

    public function appendRoles(array $roles): RoleManager
    {
        // insert roles at once
        foreach ($roles as $role) {
            $this->appendRole($role);
        }
        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public static function factory(int $uid): RoleManager
    {
        return new RoleManager($uid);
    }


}