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

    function onOpen(Websocket $connection): void;


    function onMessage(Websocket $from, string $payload, bool $binary): void;


    function onClose(Websocket $connection): void;


    function onError(Websocket $connection, \Throwable $error): void;


}
