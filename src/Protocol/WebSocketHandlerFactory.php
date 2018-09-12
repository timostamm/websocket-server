<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 09:59
 */

namespace TS\WebSockets\Protocol;


use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use function React\Promise\resolve;


/**
 *
 * Handles lifetime of a WebSocket connection.
 *
 * The underlying TCP connection may live on a
 * bit longer after a WebSocket is closed, because
 * we consider WebSockets to be closed as soon as
 * they start closing.
 *
 */
class WebSocketHandlerFactory
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


    /** @var \SplObjectStorage */
    private $handlerByTcp;

    /** @var Deferred */
    private $shuttingDown = null;


    public function __construct(array $serverParams)
    {
        $this->handlerByTcp = new \SplObjectStorage();
    }


    public function add(ServerRequestInterface $request, ConnectionInterface $tcpConnection): WebSocketHandler
    {
        $handler = new WebSocketHandler(
            $request,
            $tcpConnection,
            self::closeFrameChecker(),
            self::exceptionFactory()
        );

        $this->handlerByTcp->attach($tcpConnection, $handler);

        $tcpConnection->once('close', function () use ($tcpConnection) {
            $this->onTcpClose($tcpConnection);
        });

        if ($this->shuttingDown) {
            // close gracefully while shutting down
            $handler->startClose(Frame::CLOSE_GOING_AWAY, 'shutdown');
        }

        return $handler;
    }


    protected function onTcpClose(ConnectionInterface $tcpConnection): void
    {
        $this->handlerByTcp->detach($tcpConnection);

        if ($this->shuttingDown && $this->handlerByTcp->count() === 0) {
            $this->shuttingDown->resolve();
        }
    }


    public function shutDown(): PromiseInterface
    {
        if ($this->handlerByTcp->count() === 0) {
            return resolve();
        }

        $this->shuttingDown = new Deferred();

        foreach ($this->handlerByTcp as $tcpConnection) {

            /** @var WebSocketHandler $handler */
            $handler = $this->handlerByTcp[$tcpConnection];

            if (!$handler->isClosing() && !$handler->isClosed()) {
                $handler->startClose(Frame::CLOSE_GOING_AWAY, 'shutdown');
            }

        }

        return $this->shuttingDown->promise();
    }

}
