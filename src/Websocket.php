<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 07.09.18
 * Time: 20:00
 */

namespace TS\Websockets;


use Evenement\EventEmitter;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\MessageInterface;
use TS\Websockets\Connections\WebsocketHandler;


/**
 *
 * Emits:
 *
 * "message" => string $payload, bool $binary
 *
 * "error" => \Throwable $throwable
 *
 * "close"
 *
 */
class Websocket extends EventEmitter
{

    private $request;

    private $manager;


    public function __construct(WebsocketHandler $manager, ServerRequestInterface $request)
    {
        $this->manager = $manager;
        $this->request = $request;
    }


    /**
     * @param string $payload
     * @param bool $binary
     * @throws \BadMethodCallException if the connection is closed or closing
     */
    public function send(string $payload, bool $binary = false): void
    {
        $this->manager->send($payload, $binary);
    }


    public function sendData(MessageInterface $message): void
    {
        $this->manager->sendData($message);
    }


    /**
     * @param int $code
     * @throws \BadMethodCallException if the connection is already closed or closing
     */
    public function close($code = Frame::CLOSE_NORMAL): void
    {
        $this->manager->startClose($code);
    }


    /**
     * Get the HTTP request that initiated this connection.
     *
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }


    /**
     *
     * Returns true if the connection is closed or closing.
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->manager->isClosed()
            || $this->manager->isClosing();
    }


}
