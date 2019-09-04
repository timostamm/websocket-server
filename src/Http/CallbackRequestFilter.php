<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 14:43
 */

namespace TS\WebSockets\Http;


use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

class CallbackRequestFilter implements RequestFilterInterface
{

    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function apply(ServerRequestInterface $request): ServerRequestInterface
    {
        $cb = $this->callback;
        $result = $cb($request);
        if (is_null($result)) {
            return $request;
        }
        if ($result instanceof ServerRequestInterface) {
            return $result;
        }
        $msg = 'Expected request filter callback to return a ServerRequestInterface or null, got ' . gettype($result) . '.';
        throw new UnexpectedValueException($msg);
    }


}
