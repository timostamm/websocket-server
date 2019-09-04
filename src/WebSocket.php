<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 07.09.18
 * Time: 20:00
 */

namespace TS\WebSockets;


use BadMethodCallException;
use Evenement\EventEmitter;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Messaging\Frame;
use TS\WebSockets\Protocol\WebSocketHandler;


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
class WebSocket extends EventEmitter
{

    private $handler;


    public function __construct(WebSocketHandler $handler)
    {
        $this->handler = $handler;
    }


    /**
     * @param string $payload
     * @param bool $binary
     * @throws BadMethodCallException if the connection is closed or closing
     */
    public function send(string $payload, bool $binary = false): void
    {
        $this->handler->send($payload, $binary);
    }


    /**
     * @param int $code
     * @param string $reason
     */
    public function close(int $code = Frame::CLOSE_NORMAL, string $reason=''): void
    {
        $this->handler->startClose($code, $reason);
    }


    /**
     * Get the HTTP request that initiated this connection.
     *
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->handler->getRequest();
    }


    /**
     * @return null|string remote address (URI) or null if unknown
     */
    public function getRemoteAddress(): ?string
    {
        return $this->handler->getTcpConnection()->getRemoteAddress();
    }


    /**
     *
     * Returns true if the connection is closed or closing.
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->handler->isClosed()
            || $this->handler->isClosing();
    }


    public function __toString()
    {
        $address = $this->getRemoteAddress();
        $ip = trim(parse_url($address, PHP_URL_HOST), '[]');
        $port = parse_url($address, PHP_URL_PORT);
        return $ip . ':' . $port;
    }


}
