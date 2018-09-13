<?php


use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use TS\WebSockets\ControllerInterface;
use TS\WebSockets\Http\OriginFilter;
use TS\WebSockets\Http\ResponseException;
use TS\WebSockets\WebSocket;
use TS\WebSockets\WebSocketServer;


require_once __DIR__ . '/../vendor/autoload.php';


$loop = Factory::create();


$server = new WebSocketServer($loop, [
    'uri' => '127.0.0.1:23080'
]);


$server->on('error', function (Throwable $error) {
    // This error handler will be called when an exception was thrown
    // by a filter, a controller method or the underlying tcp server.
    print 'Server error: ' . $error->getMessage() . PHP_EOL;
});


// This filter responds with a HTTP 403
$server->filter('example/403', function () {
    throw ResponseException::create(403);
});


// This filter modifies the request
$server->filter('example/add-attribute', function (ServerRequestInterface $request) {
    return $request->withAttribute('X-filter', 'passed');
});


// This filter allows only the specified origins
$server->filter('example/origin', new OriginFilter(['example.com']));


// Add example route
$server->route([
    'match' => '/example/*',
    'sub_protocols' => ['my-proto'],
    'filter' => function (ServerRequestInterface $request) {
        if ($request->getRequestTarget() === '/example/forbidden') {
            throw ResponseException::create(403);
        }
    },
    'controller' => new class() implements ControllerInterface
    {

        function onOpen(WebSocket $socket): void
        {
            print $socket . ' connected. Sending a "Hello".' . PHP_EOL;
            $socket->send('Hello');
        }

        function onMessage(WebSocket $from, string $payload, bool $binary): void
        {
            print $from . ' received: ' . $payload . PHP_EOL;
        }

        function onClose(WebSocket $socket): void
        {
            print $socket . ' disconnected.' . PHP_EOL;
        }

        function onError(WebSocket $socket, \Throwable $error): void
        {
            print $socket . ' error: ' . $error->getMessage() . PHP_EOL;
        }

    }
]);


$loop->run();

