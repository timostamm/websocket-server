<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:08
 */

namespace TS\WebSockets;


use Throwable;

interface ControllerInterface
{

    /**
     * A new client connected to the server.
     *
     * @param WebSocket $socket
     */
    function onOpen(WebSocket $socket): void;


    /**
     * A client sent a message.
     *
     * @param WebSocket $from
     * @param string $payload
     * @param bool $binary
     */
    function onMessage(WebSocket $from, string $payload, bool $binary): void;


    /**
     * A client disconnected from the server.
     *
     * If a TCP error occurred, the $error parameter is provided.
     *
     * Close code and reason are available through
     * WebSocket#getCloseCode and WebSocket#getCloseReason
     *
     * @param WebSocket $socket
     * @param Throwable|null $error
     */
    function onClose(WebSocket $socket, ?Throwable $error): void;



}
