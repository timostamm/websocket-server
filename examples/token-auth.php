<?php


use React\EventLoop\Factory;
use TS\WebSockets\Http\AuthorizationFilter;
use TS\WebSockets\WebSocketServer;

require_once __DIR__ . '/autoload.php';


$loop = Factory::create();
$server = new WebSocketServer($loop, [
    'uri' => '127.0.0.1:23080',
    'sub_protocols' => ['auth-token']
]);


// If a valid token is present, set user as request attribute.
$server->filter('*', new DummyTokenAuthenticator());


// Access to restricted area requires a user request attribute.
$server->filter('/restricted/*', new AuthorizationFilter());


// Allows anybody
$server->route([
    'match' => '/public',
    'controller' => new HelloUserController()
]);


// Allows only authenticated users
$server->route([
    'match' => '/restricted/*',
    'controller' => new HelloUserController()
]);


// Allow only user "Alice"
$server->route([
    'match' => '/restricted/alice-only',
    'filter' => new AuthorizationFilter(function ($user) {
        return $user === 'Alice';
    }),
    'controller' => new HelloUserController()
]);


$server->on('error', function (Throwable $error) {
    // This error handler will be called when an exception was thrown
    // by a filter, a controller method or the underlying tcp server.
    print 'Server error: ' . $error->getMessage() . PHP_EOL;
});


$loop->run();

