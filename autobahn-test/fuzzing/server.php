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


$server->addRoute('*', new class implements ControllerInterface
{
    function onMessage(WebSocket $from, string $payload, bool $binary): void
    {
        $from->send($payload, $binary);
    }

    function onOpen(WebSocket $connection): void
    {
    }

    function onClose(WebSocket $connection): void
    {
    }

    function onError(WebSocket $connection, \Throwable $error): void
    {
    }
});


$loop->run();
