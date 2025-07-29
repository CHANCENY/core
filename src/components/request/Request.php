<?php

namespace Simp\Core\components\request;

use Symfony\Component\HttpFoundation\InputBag;

class Request extends \Symfony\Component\HttpFoundation\Request
{
    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        parent::__construct($_GET, $_POST, $attributes, $_COOKIE, [], $_SERVER, file_get_contents('php://input'));
    }

    protected static function createRequestFromFactory(array $query, array $request, array $attributes, array $cookies, array $files, array $server, $content = null): static {
        return new static($query, $request, $attributes, $cookies, $files, $server, $content);
    }

    public static function createFromGlobals(): static
    {
        $request = self::createRequestFromFactory($_GET, $_POST, [], $_COOKIE, [], $_SERVER);
        if (str_starts_with($request->headers->get('CONTENT_TYPE', ''), 'application/x-www-form-urlencoded')
            && \in_array(strtoupper($request->server->get('REQUEST_METHOD', 'GET')), ['PUT', 'DELETE', 'PATCH'], true)
        ) {
            parse_str($request->getContent(), $data);
            $request->request = new InputBag($data);
        }

        return $request;
    }
}