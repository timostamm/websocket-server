<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 15:24
 */

namespace TS\WebSockets\Http;


use Psr\Http\Message\ServerRequestInterface;


/**
 *
 * By default, this filter simply checks if the
 * request attribute "user" has a value.
 *
 * If "user" is not present, a HTTP 401 Unauthorized
 * is thrown.
 *
 * If the $checkUser argument is provided, the
 * function is called with the "user" value as an
 * argument.
 *
 * If $checkUser returns false, a HTTP 403 Forbidden
 * is thrown. Otherwise, the request passes on.
 *
 */
class AuthorizationFilter implements RequestFilterInterface
{

    private $checkUser;

    public function __construct(callable $checkUser = null)
    {
        $this->checkUser = $checkUser;
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
        $fn = $this->checkUser;
        if (is_null($fn)) {
            return true;
        }
        return $fn($user);
    }


}


