<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 15:29
 */

namespace TS\WebSockets\Http;


use Psr\Http\Message\ServerRequestInterface;


/**
 *
 * Unfortunately, the websocket spec does not allow to
 * set custom headers like an authorization header with
 * an access token.
 *
 * Cookies are available, but if you need tokens, you
 * can pass them to the server as a subprototol:
 *
 * JS:
 *
 * var token = '3gn11Ft0Me8lkqqW2/5uFQ=';
 * new WebSocket("ws://localhost:8080/stream", ['auth-token', 'auth-token-'+token]);
 *
 *
 * On the server:
 *
 * $server->route([
 *   'protocols' => ['auth-token']
 * ]);
 *
 * class MyTokenAuth extends AbstractTokenAuthenticator {
 *  protected function decodeToken(string $token) {
 *      // decode token and return an object representing a user
 *      // return null for a 401 response or throw your own ResponseException
 *   }
 * }
 *
 * $server->filter('*', new MyTokenAuth('auth-token-'));
 *
 * Now the requests have a "user" attribute.
 *
 * If you want detailed control over authorization, see
 * AuthorizationFilter.
 *
 */
abstract class AbstractTokenAuthenticator implements RequestFilterInterface
{

    private $protoTokenPrefix;


    public function __construct(string $protoTokenPrefix = 'auth-token-')
    {
        $this->protoTokenPrefix = $protoTokenPrefix;
    }


    public function apply(ServerRequestInterface $request): ServerRequestInterface
    {
        $token = $this->findToken($request);
        if (is_null($token)) {
            throw ResponseException::create(401);
        }
        $user = $this->decodeToken($token);
        if (!$user) {
            throw ResponseException::create(401);
        }
        return $request
            ->withAttribute('token', $token)
            ->withAttribute('user', $user);
    }


    /**
     * Override this method and decode / verify the token.
     * Return a user object that will be made available
     * in the request attribute "user".
     *
     * @param string $token
     * @return mixed
     */
    abstract protected function decodeToken(string $token);


    protected function findToken(ServerRequestInterface $request): ?string
    {
        $protocols = $request->getHeader('Sec-WebSocket-Protocol');
        $protocols = array_map('trim', explode(',', implode(',', $protocols)));
        foreach ($protocols as $item) {
            if (strpos($item, $this->protoTokenPrefix) === 0) {
                $token = substr($item, strlen($this->protoTokenPrefix));
                if ($token !== '') {
                    return $token;
                }
            }
        }
        return null;
    }


}
