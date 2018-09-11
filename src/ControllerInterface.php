<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:08
 */

namespace TS\WebSockets;


interface ControllerInterface
{

    function onOpen(WebSocket $socket): void;


    function onMessage(WebSocket $from, string $payload, bool $binary): void;


    function onClose(WebSocket $socket): void;


    function onError(WebSocket $socket, \Throwable $error): void;


}
