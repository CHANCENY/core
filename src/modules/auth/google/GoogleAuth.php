<?php

namespace Simp\Core\modules\auth\google;

use Google\Service\Oauth2\Userinfo;
use Google\Client;
use Google\Service\Exception;
use Google\Service\Oauth2;
use Simp\Core\modules\auth\AuthenticationSystem;
use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service;

class GoogleAuth
{
    private string $client_id;
    private string $client_secret;
    private string $redirect_uri;
    private array $scope;

    private $client;

    public function __construct()
    {
        $auth = new AuthenticationSystem();
        $credential = $auth->getGoogleOauthCredentials();
        $this->client_id = $credential->client_id;
        $this->client_secret = $credential->client_secret;
        $this->redirect_uri = $credential->redirect;
        $this->scope = $credential->scope;
        $this->client = new Client();
        $this->client->setClientId($this->client_id);
        $this->client->setClientSecret($this->client_secret);

        $request = Service::serviceManager()->request;
        $schema = trim($request->getSchemeAndHttpHost(), '/');
        $this->client->setRedirectUri($schema.'/'. trim($this->redirect_uri, '/'));
        $this->client->setScopes($this->scope);

    }

    public function generateLoginUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function fetchAccessTokenWithAuthCode(string $code): array
    {
       return $this->client->fetchAccessTokenWithAuthCode($code);
    }

    public function setAccessToken(array $accessToken): void
    {
        $this->client->setAccessToken($accessToken['access_token']);
    }

    /**
     * @throws Exception
     */
    public function oauth2Profile(): Userinfo
    {
        $oauth = new Oauth2($this->client);
        return $oauth->userinfo->get();
    }

    public function getClientId(): string
    {
        return $this->client_id;
    }

    public function getClientSecret(): string
    {
        return $this->client_secret;
    }

    public function getRedirectUri(): string
    {
        return $this->redirect_uri;
    }

    public function getScope(): array
    {
        return $this->scope;
    }

}