<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 09:59
 */

namespace TS\Websockets\Connections;


use Psr\Http\Message\ServerRequestInterface;
use React\Socket\ConnectionInterface;
use TS\Websockets\Websocket;


interface ConnectionFactoryInterface
{

    function createConnection(ServerRequestInterface $request, ConnectionInterface $tcpConnection): Websocket;


}
