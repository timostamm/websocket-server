<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:08
 */

namespace TS\Websockets;


interface ControllerInterface
{

    function onOpen(WebSocket $connection): void;


    function onMessage(WebSocket $from, string $payload, bool $binary): void;


    function onClose(WebSocket $connection): void;


    function onError(WebSocket $connection, \Throwable $error): void;


}
