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
        $oauth_file = $system->webroot_dir .DIRECTORY_SEPARATOR . 'core'.DIRECTORY_SEPARATOR . 'defaults' . DIRECTORY_SEPARATOR . 'oauth'
            . DIRECTORY_SEPARATOR . 'oauth.yml';

        if (file_exists($oauth_file)) {
            $this->oauth = Yaml::parse(file_get_contents($oauth_file), Yaml::PARSE_OBJECT_FOR_MAP);
        }

        $outh_custom = $system->setting_dir . DIRECTORY_SEPARATOR . 'oauth.yml';
        if (file_exists($outh_custom)) {
            $this->oauth = Yaml::parse(file_get_contents($outh_custom), Yaml::PARSE_OBJECT_FOR_MAP);
        }
    }

    public function isNormalAuthActive(): bool
    {
        return $this->oauth->active === "normal";
    }

    public function isGoogleAuthActive(): bool
    {
        return !empty($this->oauth->google->credential->client_id) && !empty($this->oauth->google->credential->client_secret);
    }

    public function isGithubAuthActive(): bool
    {
        return !empty($this->oauth->github->credential->client_id) && !empty($this->oauth->github->credential->client_secret);
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

    public static function addSetting(array $setting)
    {
        $system = new SystemDirectory();
        $outh_custom = $system->setting_dir . DIRECTORY_SEPARATOR . 'oauth.yml';
        if (!file_exists($outh_custom)) {
            touch($outh_custom);
        }
        $settings = Yaml::parse(file_get_contents($outh_custom)) ?? [];
        $settings = array_merge($settings, $setting);
        file_put_contents($outh_custom, Yaml::dump($settings));
    }

    public static function getSetting()
    {
        $system = new SystemDirectory();
        $outh_custom = $system->setting_dir . DIRECTORY_SEPARATOR . 'oauth.yml';

        if (file_exists($outh_custom)) {
            return Yaml::parseFile($outh_custom);
        }

        $oauth_file = $system->webroot_dir .DIRECTORY_SEPARATOR . 'core'.DIRECTORY_SEPARATOR . 'defaults' . DIRECTORY_SEPARATOR . 'oauth'
            . DIRECTORY_SEPARATOR . 'oauth.yml';

        if (file_exists($oauth_file)) {
            return Yaml::parseFile($oauth_file);
        }

        return [];
    }
}