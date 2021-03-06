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
 * The websocket spec does not allow to set custom headers
 * like an authorization header with an access token.
 *
 * As a workaround, tokens can be provided as a subprotocol:
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
 *   'sub_protocols' => ['auth-token']
 * ]);
 *
 * $server->filter('*', new MyTokenAuth('auth-token-'));
 *
 * This class looks for a subprotocol with a specific prefix,
 * and takes the remainder as a token.
 *
 */
abstract class AbstractTokenAuthenticator implements RequestFilterInterface
{

    /** @var string */
    private $protoTokenPrefix;


    public function __construct(string $protoTokenPrefix = 'auth-token-')
    {
        $this->protoTokenPrefix = $protoTokenPrefix;
    }


    public function apply(ServerRequestInterface $request): ServerRequestInterface
    {
        $token = $this->findToken($request);
        if (is_null($token)) {
            return $request
                ->withoutAttribute('token')
                ->withoutAttribute('user');
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
