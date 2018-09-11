<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 09:59
 */

namespace TS\Websockets\Connections;


use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use React\Socket\ConnectionInterface;


class HandlerFactory
{

    private static $closeFrameCheckerSingleton;
    private static $exceptionFactory;
    private static $sharedException;


    private static function closeFrameChecker(): CloseFrameChecker
    {
        if (!self::$closeFrameCheckerSingleton) {
            self::$closeFrameCheckerSingleton = new CloseFrameChecker();
        }
        return self::$closeFrameCheckerSingleton;
    }


    private static function exceptionFactory(): callable
    {
        if (!self::$exceptionFactory) {
            self::$sharedException = new \UnderflowException();
            self::$exceptionFactory = function () {
                return self::$sharedException;
            };
        }
        return self::$exceptionFactory;
    }


    public function create(ServerRequestInterface $request, ConnectionInterface $tcpConnection): WebSocketHandler
    {
        $handler = new WebSocketHandler(
            $request,
            $tcpConnection,
            self::closeFrameChecker(),
            self::exceptionFactory()
        );
        return $handler;
    }

}
