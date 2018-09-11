<?php


use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use TS\Websockets\ControllerInterface;
use TS\Websockets\Http\OriginFilter;
use TS\Websockets\Http\ResponseException;
use TS\Websockets\WebSocket;
use TS\Websockets\WebSocketServer;


require_once __DIR__ . '/../vendor/autoload.php';


$loop = Factory::create();


$server = new WebSocketServer($loop, [
    'uri' => '127.0.0.1:23080',
    'on_error' => function (Throwable $error) {
        // This error handler will be called when an exception was thrown
        // by a filter, a controller method or the underlying tcp server.
        print 'Server error: ' . $error->getMessage() . PHP_EOL;
    }
]);


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
    'protocols' => ['my-proto'],
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
            print $from . ' sent: ' . $payload . PHP_EOL;
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


// Alternative route definition, controller will be created for you
$server->route([
    'match' => '/other/*',
    'protocols' => ['my-proto'],
    'on_message' => function (WebSocket $from, string $payload, bool $binary): void {
        print 'Message from ' . $from->getRemoteAddress() . ": " . $payload . PHP_EOL;
    }
    // on_open, on_close, on_error are available too
]);


// Alternative to the "on_error" option
// $server->on('error', function(Throwable $error){
//     print 'Server error: ' . $error->getMessage() . PHP_EOL;
// });


$loop->run();
