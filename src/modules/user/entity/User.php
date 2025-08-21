<?php

namespace Simp\Core\modules\user\entity;

use Exception;
use PDO;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Random\RandomException;
use Simp\Core\components\site\SiteManager;
use Simp\Core\modules\config\ConfigManager;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\mail\MailQueueManager;
use Simp\Core\modules\tokens\TokenManager;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Core\modules\user\profiles\Profile;
use Simp\Core\modules\user\roles\Role;
use Simp\Core\modules\user\roles\RoleManager;
use Simp\Core\modules\user\trait\StaticHelperTrait;
use Simp\Mail\Mail\Envelope;

class User
{
    use StaticHelperTrait;
    public function __construct(protected ?int $uid, protected ?string $name, protected ?string $mail,
                                protected ?string $password, protected ?string $created, protected ?string $updated,
                                protected ?string $login, protected bool|int|null $status){}

    public static function load(int $uid): ?User
    {
        $query = "SELECT * FROM `users` WHERE `uid` = :uid";
        $query = Database::database()->con()->prepare($query);
        $query->bindValue('uid', $uid, PDO::PARAM_INT);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);
        if (empty($result)) {
            return null;
        }
        return new User(...$result);
    }

    public static function loadAnonymous(): User
    {
        $account_setting = ConfigManager::config()->getConfigFile("account.setting");
        return new User(0, $account_setting?->get('anonymous_name', "Guest user"), null, null, null, null, null, 1);
    }

    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'name' => $this->name,
            'created' => $this->created,
            'updated' => $this->updated,
            'login' => $this->login,
            'status' => $this->status,
            'profile' => $this->getProfile()->toArray()

        ];
    }

    public static function loadByMail(string $mail): ?User
    {
        $query = "SELECT * FROM `users` WHERE `mail` = :mail";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':mail', $mail, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);
        if (empty($result)) {
            return null;
        }
        return new User(...$result);
    }

    public static function loadByName(string $name): ?User
    {
        $query = "SELECT * FROM `users` WHERE `name` = :name";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':name', $name, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);
        if (empty($result)) {
            return null;
        }
        return new User(...$result);
    }

    /**
     * @param array $data
     * @return User|false|null
     * False is returned if name or mail already exist.
     * Null if keys name, mail, password, time_zone are not set
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws Exception
     */
    public static function create(array $data): User|null|false
    {
        $query = "INSERT INTO users (name, mail, password, status) VALUES (:name, :mail, :password, :status)";
        $query_profile = "INSERT INTO user_profile (uid, time_zone) VALUES (:uid, :time_zone)";
        $query_role = "INSERT INTO user_roles (name, role_name, role_label, uid) VALUES (:name, :role_name, :role_label, :uid)";
        $connection = Database::database()->con();

        // Bring in settings
        $account_setting = ConfigManager::config()->getConfigFile("account.setting");
        if ($account_setting?->get('register')) {

            $allowed = $account_setting?->get('allow_account_creation');
            $current_user = CurrentUser::currentUser()->getUser();

            if(!in_array($allowed, array_map(fn($item) => $item->getRoleName(), $current_user->getRoles()))) {
                return false;
            }

        }

        $emails = [];
        if ($account_setting?->get('verification_email') === 'yes') {
            $emails['verifying'] = "Hello [user:name] you have created account on  [site:name] and you need to verify your email address. Please click the link below to verify your email address. [site:url]/user/verify/[user:verify_token]";
        }
        if (trim($account_setting?->get('account_creation_message',''))) {
            $emails['creation'] = $account_setting?->get('account_creation_message','');
        }
        if ($account_setting?->get('notifications')) {
            $emails['notifications'] = "Hello new user has created account on [site:name]. Please click the link below to view the new user. [site:url]/user/[user:uid]";
        }

        // Creation user first so that we can have uid value.
        $uid = 0;
        if (!empty($data['name']) && !empty($data['mail']) && !empty($data['password']) && !empty($data['time_zone'])) {

            // Hash password
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);

            // checking the if email or name exist already.
            if (self::loadByMail($data['mail']) !== null || self::loadByName($data['name']) !== null) {
                return false;
            }
            $config = ConfigManager::config()->getConfigFile("account.setting");
            if ($config?->get('verification_email') === 'no') {
                $data['status'] = 1;
            }
            else {
                $data['status'] = $config?->get('allow_account_creation') === 'visitor-pending' ? 0 : 1;
            }
            $statement = $connection->prepare($query);
            $statement->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $statement->bindParam(':mail', $data['mail'], PDO::PARAM_STR);
            $statement->bindParam(':password', $data['password'], PDO::PARAM_STR);
            $statement->bindParam(':status', $data['status'], PDO::PARAM_INT);
            $statement->execute();
            $uid = $connection->lastInsertId();
        }

        // If uid is created then lets create the profile and roles.
        if (!empty($uid)) {

            // Check if roles are set in array
            if (!empty($data['roles']) && is_array($data['roles'])) {
                foreach ($data['roles'] as $role) {
                    $statement = $connection->prepare($query_role);
                    $statement->bindParam(':uid', $uid, PDO::PARAM_INT);
                    $statement->bindParam(':role_name', $role, PDO::PARAM_STR);
                    $statement->bindParam(':role_label', $role, PDO::PARAM_STR);
                    $statement->bindParam(':name', $role, PDO::PARAM_STR);
                    $statement->execute();
                }
            }
            elseif (!empty($data['roles']) && is_string($data['roles'])) {
                $role = $data['roles'];
                $statement = $connection->prepare($query_role);
                $statement->bindParam(':uid', $uid, PDO::PARAM_INT);
                $statement->bindParam(':role_name', $role, PDO::PARAM_STR);
                $statement->bindParam(':role_label', $role, PDO::PARAM_STR);
                $statement->bindParam(':name', $role, PDO::PARAM_STR);
                $statement->execute();
            }
            else {
                $role = "authenticated";
                $statement = $connection->prepare($query_role);
                $statement->bindParam(':uid', $uid, PDO::PARAM_INT);
                $statement->bindParam(':role_name', $role, PDO::PARAM_STR);
                $statement->bindParam(':role_label', $role, PDO::PARAM_STR);
                $statement->bindParam(':name', $role, PDO::PARAM_STR);
                $statement->execute();
            }

            // Lets create profile.
            $statement = $connection->prepare($query_profile);
            $statement->bindParam(':uid', $uid, PDO::PARAM_INT);
            $statement->bindParam(':time_zone', $data['time_zone'], PDO::PARAM_STR);
            $statement->execute();

            $user = self::load($uid);
           if ($emails) {

               if ($emails['verifying']) {
                  MailQueueManager::factory()->add(Envelope::create(
                      'Verifying your email address',
                      TokenManager::token()->resolver($emails['verifying'], ['site'=>SiteManager::factory(), 'user'=>$user]),
                  )->addToAddresses([$user->getMail()]));
               }
               if ($emails['creation']) {
                   MailQueueManager::factory()->add( Envelope::create(
                       'Account creation',
                       TokenManager::token()->resolver($emails['creation'], ['site'=>SiteManager::factory(), 'user'=>$user]),
                   )->addToAddresses([$user->getName()]));
               }
               if ($emails['notifications']) {
                   MailQueueManager::factory()->add(Envelope::create(
                       'New user',
                       TokenManager::token()->resolver($emails['notifications'], ['site'=>SiteManager::factory(), 'user'=>$user, 'settings'=>$account_setting]),
                   )->addToAddresses([$account_setting?->get('notifications')]));
               }
           }
           return $user;
        }
        return null;
    }

    public function getRoles(): array
    {
        if ($this->uid === 0) {
            return [
                new Role(0, 'anonymous', 0,'anonymous', 'anonymous'),
            ];
        }
        return $this->roleManager()->getRoles();
    }

    public function getProfile(): ?Profile
    {
        if ($this->uid === 0) {
            return new Profile(0,'Guest','Profile',0,0,null,null,0,'en');
        }
        $query = "SELECT * FROM `user_profile` WHERE `uid` = :uid";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':uid', $this->uid, PDO::PARAM_INT);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);
        if (empty($result)) {
            return null;
        }
        return new Profile(...$result);
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function getName(): ?string
    {
        $account_setting = ConfigManager::config()->getConfigFile("account.setting");
        return $this->name;
    }

    public function getMail(): ?string
    {
        return $this->mail;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @throws RandomException
     */
    public function getVerifyEmailToken(): ?string {

        $token = random_bytes(32);
        $token = bin2hex($token);
        $query = "INSERT INTO verify_email_token (token, uid) VALUES (:token, :uid) ON DUPLICATE KEY UPDATE token = :token_update";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':token', $token, PDO::PARAM_STR);
        $query->bindParam(':uid', $this->uid, PDO::PARAM_INT);
        $query->bindParam(':token_update', $token, PDO::PARAM_STR);
        $query->execute();
        return $token;

    }

    public function getCreated(): ?string
    {
        return $this->created;
    }

    public function getUpdated(): ?string
    {
        return $this->updated;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function __get(string $name)
    {
        return $this->$name;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function setMail(?string $mail): void
    {
        $this->mail = $mail;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function setLogin(?string $login): void
    {
        $this->login = $login;
    }

    public function setStatus(?bool $status): void
    {
        $this->status = $status;
    }

    public function update(): bool
    {
        $query = "UPDATE `users` SET mail = :mail, name = :name, password = :password, status = :status, login = :login WHERE uid = :uid";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':mail', $this->mail, PDO::PARAM_STR);
        $query->bindParam(':name', $this->name, PDO::PARAM_STR);
        $query->bindParam(':password', $this->password, PDO::PARAM_STR);
        $query->bindParam(':status', $this->status, PDO::PARAM_INT);
        $query->bindParam(':uid', $this->uid, PDO::PARAM_INT);
        $query->bindParam(':login', $this->login, PDO::PARAM_STR);
        return $query->execute();
    }

    public static function filter(string $name) {
        $query = "SELECT * FROM users WHERE name LIKE :name OR mail LIKE :mail";
        $query = Database::database()->con()->prepare($query);
        $name = '%'.strip_tags($name).'%';
        $query->bindParam(':name', $name);
        $query->bindParam(':mail', $name);
        $query->execute();
        return $query->fetchAll();
    }

    public function assignRole(string $role): bool
    {
        $query = "INSERT INTO `user_roles` (`uid`, `role_name`,`role_label`, `name`) VALUES (:uid, :role_name, :role_label, :name)";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':uid', $this->uid, PDO::PARAM_INT);
        $query->bindParam(':role_name', $role, PDO::PARAM_STR);
        $query->bindParam(':role_label', $role, PDO::PARAM_STR);
        $query->bindParam(':name', $role, PDO::PARAM_STR);
        return $query->execute();
    }

    public function unassignRole(string $role): bool
    {
        $query = "DELETE FROM `user_roles` WHERE `uid` = :uid AND `role_name` = :role_name";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':uid', $this->uid, PDO::PARAM_INT);
        $query->bindParam(':role_name', $role, PDO::PARAM_STR);
        return $query->execute();
    }

    public function delete(): bool
    {
        $query = "DELETE FROM `users` WHERE `uid` = :uid";
        $query = Database::database()->con()->prepare($query);
        $query->bindParam(':uid', $this->uid, PDO::PARAM_INT);
        return $query->execute();
    }

    public function roleManager(): RoleManager
    {
        return new RoleManager($this->uid);
    }
}