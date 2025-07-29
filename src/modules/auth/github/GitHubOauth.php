<?php

namespace Simp\Core\modules\auth\github;

use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Simp\Core\modules\auth\AuthenticationSystem;
use Symfony\Component\HttpFoundation\Request;
use Simp\Core\modules\services\Service;

class GitHubOauth
{
    protected string $client_id;
    protected string $client_secret;
    protected string $redirect_uri;
    private Github $github;

    public function __construct()
    {
        $auth = new AuthenticationSystem();
        $credential = $auth->getGithubOauthCredentials();
        $this->client_id = $credential->client_id;
        $this->client_secret = $credential->client_secret;
        $this->redirect_uri = $credential->redirect;

        $request = Service::serviceManager()->request;
        $schema = trim($request->getSchemeAndHttpHost(), '/');

        $this->github = new Github([
            'clientId' => $this->client_id,
            'clientSecret' => $this->client_secret,
            'redirectUri' => $schema.'/'. trim($this->redirect_uri, '/'),
        ]);
    }

    public function generateLoginUrl(): string
    {
        return $this->github->getAuthorizationUrl();
    }

    /**
     * @throws GuzzleException
     * @throws IdentityProviderException
     */
    public function getAccessToken(string $code): AccessTokenInterface|AccessToken
    {
        return $this->github->getAccessToken('authorization_code', [
            'code' => $code
        ]);
    }

    /**
     * @throws GuzzleException
     * @throws IdentityProviderException
     */
    public function getResourceOwner(AccessToken $token): ResourceOwnerInterface
    {
        return $this->github->getResourceOwner($token);
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

    public function getGithub(): Github
    {
        return $this->github;
    }

}