<?php

use TS\WebSockets\Http\AbstractTokenAuthenticator;


/**
 *
 * A dummy.
 *
 * A real implementation should use a JWT library to
 * decode and verify the token and return a user object.
 *
 */
class DummyTokenAuthenticator extends AbstractTokenAuthenticator
{
    protected function decodeToken(string $token)
    {
        $token = rawurldecode($token);
        $user = base64_decode($token);
        return is_string($user) ? $user : null;
    }
}

