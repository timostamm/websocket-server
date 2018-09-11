<?php

use React\EventLoop\LoopInterface;
use TS\WebSockets\WebSocket;
use TS\WebSockets\WebSocketServer;

require_once __DIR__ . '/../../vendor/autoload.php';


/** @var LoopInterface $loop */
$uri = sprintf('127.0.0.1:%s', $argv[1] ?? 8000);
$loopClass = sprintf('React\EventLoop\%s', $argv[2] ?? 'StreamSelectLoop');
$loop = new $loopClass;
$server = new WebSocketServer($loop, ['uri' => $uri]);

print '[server.php] Websocket server using ' . $loopClass . ' listening on ' . $server->getAddress() . PHP_EOL;

$server->route([
    'on_message' => function (WebSocket $from, string $payload, bool $binary) {
        $from->send($payload, $binary);
    }
]);


$loop->run();
