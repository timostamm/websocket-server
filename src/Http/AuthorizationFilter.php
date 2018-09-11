<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 15:24
 */

namespace TS\WebSockets\Http;


use Psr\Http\Message\ServerRequestInterface;


class AuthorizationFilter implements RequestFilterInterface
{

    private $isAuthorized;

    public function __construct(callable $isAuthorized = null)
    {
        $this->isAuthorized = $isAuthorized;
    }

    public function apply(ServerRequestInterface $request): ServerRequestInterface
    {
        $user = $request->getAttribute('user');
        if (!$user) {
            throw ResponseException::create(401);
        }
        if (!$this->isAuthorized($user)) {
            throw ResponseException::create(403);
        }
        return $request;
    }


    protected function isAuthorized($user): bool
    {
        $fn = $this->isAuthorized;
        return $fn ? $fn($user) : false;
    }


}


