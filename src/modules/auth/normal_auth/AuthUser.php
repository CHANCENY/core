<?php

namespace Simp\Core\modules\auth\normal_auth;

use DateTime;
use Google\Service\Oauth2\Userinfo;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheIOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\lib\memory\session\Session;
use Simp\Core\modules\user\entity\User;
use Simp\Core\modules\user\roles\Role;

class AuthUser
{
    protected string $name  = '';

    protected string $password = '';

    protected User $user;
    private bool $validated = false;
    private int $rememberMe = 3600;
    private bool $is_authenticated  = false;
    private bool $is_admin = false;
    private bool $is_login = false;
    private bool $is_manager = false;
    private bool $is_content_creator = false;
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string{
        return $this->name;
    }
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
    public function getPassword(): string
    {
        return $this->password;
    }
    public function authenticate(string $username, string $password): bool
    {
        if (!empty($username) && !empty($password)) {

            $user = User::loadByMail($username);
            if ($user instanceof User) {
                if (password_verify($password, $user->getPassword())) {
                    $this->user = $user;
                    $this->validated = true;
                    $this->is_login = true;
                    return true;
                }
            }

            $user = User::loadByName($username);
            if ($user instanceof User) {
                if (password_verify($password, $user->getPassword())) {
                    $this->user = $user;
                    $this->validated = true;
                    $this->is_login = true;
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheIOException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function finalizeAuthenticate(int $to_remember = 525600): void
    {
        if ($this->validated) {
            $remember_key = hash('sha512', $this->user->getPassword());
            $roles = $this->user->getRoles();
            $this->rememberMe = $to_remember;

            foreach ($roles as $role) {
                if ($role instanceof Role) {

                    if ($role->getRoleName() === "administrator") {
                        $this->is_admin = true;
                    }
                    if ($role->getRoleName() === "authenticated") {
                        $this->is_authenticated = true;
                    }
                    if ($role->getRoleName() === "manager") {
                        $this->is_manager = true;
                    }
                    if ($role->getRoleName() === "content_creator") {
                        $this->is_content_creator = true;
                    }
                }
            }
            $login_time = new DateTime();
            $this->user->setLogin($login_time->format('Y-m-d H:i:s'));
            if ($this->user->update()) {
                $this->is_login = true;
            }
            Session::init()->set("private.current.user", $this, $to_remember);
        }
    }

    public function authenticateViaGoogle(Userinfo $user): bool
    {
        $email = $user->getEmail();
        if ($user_system = User::loadByMail($email)) {
            $this->user = $user_system;
            $this->validated = true;
            $this->is_login = true;
            return true;
        }
        else {
            $name = explode('@', $email);
            $new_user = User::create([
                'mail' => $email,
                'password' => uniqid(),
                'name' => $name[0],
                'roles' => [
                    'authenticated',
                ],
                'time_zone' => 'Africa/Blantyre',
            ]);

            if ($new_user instanceof User) {
                $profile = $new_user->getProfile();
                $profile->setFirstName($user->getGivenName());
                $profile->setLastName($user->getFamilyName());

                $this->user = $new_user;
                $this->is_login = true;
                $this->validated = true;
                return true;
            }
        }
        return false;
    }

    public function authenticateViaGithub(GithubResourceOwner|ResourceOwnerInterface  $user): bool
    {
        $email = $user->getEmail();
        if ($user_system = User::loadByMail($email)) {
            $this->user = $user_system;
            $this->validated = true;
            $this->is_login = true;
            return true;
        }

        $new_user = User::create([
            'mail' => $email,
            'password' => uniqid(),
            'name' => $user->getNickname(),
            'roles' => [
                'authenticated',
            ],
            'time_zone' => 'Africa/Blantyre',
        ]);
        if ($new_user instanceof User) {
            $profile = $new_user->getProfile();
            $list = explode(' ', $user->getName());
            $profile->setFirstName($list[0]);
            if (count($list) >= 2) {
                $profile->setLastName($list[1]);
            }
            $profile->update();
            $this->user = $new_user;
            $this->is_login = true;
            $this->validated = true;
            return true;
        }
        return false;
    }

    public function __get(string $name)
    {
        return $this->$name;
    }

    public function getUser(): User
    {
        return $this->user ?? User::loadAnonymous();
    }

    public function isValidated(): bool
    {
        return $this->validated;
    }

    public function isRememberMe(): int
    {
        return $this->rememberMe;
    }

    public function isIsAuthenticated(): bool
    {
        return $this->is_authenticated;
    }

    public function isIsAdmin(): bool
    {
        return $this->is_admin;
    }

    public function isIsLogin(): bool
    {
        return $this->is_login;
    }

    public function isIsManager(): bool
    {
        return $this->is_manager;
    }

    public function isIsContentCreator(): bool
    {
        return $this->is_content_creator;
    }

    public static function auth(): AuthUser
    {
        return new AuthUser();
    }

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function logout(): bool {

       return Session::init()->delete("private.current.user");
    }

}