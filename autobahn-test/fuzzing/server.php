<?php

use TS\Websockets\WebSocketServer;
use TS\Websockets\ControllerInterface;
use TS\Websockets\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;

require_once __DIR__ . '/../../vendor/autoload.php';


$port = $argc > 1 ? $argv[1] : 8000;
$impl = sprintf('React\EventLoop\%s', $argc > 2 ? $argv[2] : 'StreamSelectLoop');
$loop = new $impl;

$server = new WebSocketServer($loop, [
    'uri' => '127.0.0.1:' . $port
]);

print 'Websocket server using ' . $impl . ' listening on ' . $server->getAddress() . PHP_EOL;

$server->addRoute('*', new class implements ControllerInterface {
    function onMessage(WebSocket $from, string $payload, bool $binary): void
    {
        $from->send($payload, $binary);
    }
    function onOpen(WebSocket $connection): void {}
    function onClose(WebSocket $connection): void {}
    function onError(WebSocket $connection, \Throwable $error): void {}
});


// This is enabled to test https://github.com/ratchetphp/Ratchet/issues/430
// The time is left at 10 minutes so that it will not try to every ping anything
// This causes the Ratchet server to crash on test 2.7
//$wsServer->enableKeepAlive($loop, 600);

//$app = new Ratchet\Http\HttpServer($wsServer);

//$server = new Ratchet\Server\IoServer($app, $sock, $loop);

$loop->run();

