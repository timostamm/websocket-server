<?php


use React\EventLoop\Factory;
use TS\Websockets\ControllerInterface;
use TS\Websockets\Websocket;
use TS\Websockets\WebsocketServer;


require_once __DIR__ . '/vendor/autoload.php';


$loop = Factory::create();

$server = new WebsocketServer($loop, [
    'uri' => '127.0.0.1:23080'
]);


$server->addRoute('*', new class() implements ControllerInterface
{

    function onOpen(Websocket $connection): void
    {
        $connection->send('Hello');
    }

    function onMessage(Websocket $from, string $payload, bool $binary): void
    {}

    function onClose(Websocket $connection): void
    {}

    function onError(Websocket $connection, \Throwable $error): void
    {
        print $error->getMessage() . PHP_EOL;
    }

});


$loop->run();
