<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 23:55
 */

namespace TS\WebSockets\Routing;


use TS\WebSockets\WebSocket;


interface OnLastCloseInterface
{

    function onLastClose(WebSocket $socket): void;

}