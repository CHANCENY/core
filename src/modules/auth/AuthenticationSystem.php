<?php

namespace Simp\Core\modules\auth;

use Simp\Core\lib\installation\SystemDirectory;
use Simp\Core\modules\auth\github\GitHubOauth;
use Simp\Core\modules\auth\google\GoogleAuth;
use Simp\Core\modules\auth\normal_auth\AuthUser;
use stdClass;
use Symfony\Component\Yaml\Yaml;

class AuthenticationSystem
{
    protected object $oauth;

    public function __construct()
    {
        $this->oauth = new StdClass();
        $system = new SystemDirectory();
        $oauth_file = $system->setting_dir .DIRECTORY_SEPARATOR . 'defaults' . DIRECTORY_SEPARATOR . 'oauth'
            . DIRECTORY_SEPARATOR . 'oauth.yml';

        if (file_exists($oauth_file)) {
            $this->oauth = Yaml::parse(file_get_contents($oauth_file), Yaml::PARSE_OBJECT_FOR_MAP);
        }
    }

    public function isNormalAuthActive(): bool
    {
        return $this->oauth->active === "normal";
    }

    public function isGoogleAuthActive(): bool
    {
        return $this->oauth->google->default === "google";
    }

    public function isGithubAuthActive(): bool
    {
        return $this->oauth->github->default === "github";
    }

    public function isNormalDefaultPasswordType(): bool
    {
        return $this->oauth->normal->default === "password";
    }

    public function isNormalDefaultPasswordLessType(): bool
    {
        return $this->oauth->normal->default === "password-less";
    }

    public function getGoogleOauthCredentials(): stdClass
    {
        return $this->oauth->google->credential;
    }

    public function getGithubOauthCredentials(): stdClass
    {
        return $this->oauth->github->credential;
    }

    public function getOauthInstance(string $tpe): AuthUser|GoogleAuth|GitHubOauth|null
    {
        return match ($tpe) {
            'google' => new GoogleAuth(),
            'github' => new GitHubOauth(),
            'normal' => new AuthUser(),
            default => null,
        };
    }
}