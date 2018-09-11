<?php

use React\EventLoop\LoopInterface;
use TS\Websockets\ControllerInterface;
use TS\Websockets\WebSocket;
use TS\Websockets\WebSocketServer;

require_once __DIR__ . '/../../vendor/autoload.php';


/** @var LoopInterface $loop */
$uri = sprintf('127.0.0.1:%s', $argv[1] ?? 8000);
$loopClass = sprintf('React\EventLoop\%s', $argv[2] ?? 'StreamSelectLoop');
$loop = new $loopClass;
$server = new WebSocketServer($loop, ['uri' => $uri]);

print '[server.php] Websocket server using ' . $loopClass . ' listening on ' . $server->getAddress() . PHP_EOL;


$server->route('*', new class implements ControllerInterface
{
    function onMessage(WebSocket $from, string $payload, bool $binary): void
    {
        $from->send($payload, $binary);
    }

    function onOpen(WebSocket $socket): void
    {
    }

    function onClose(WebSocket $socket): void
    {
    }

    function onError(WebSocket $socket, \Throwable $error): void
    {
    }
});


$loop->run();
