# WebSocket Server

A simple nonblocking server dedicated to websockets.

- upgrades HTTP requests
- routes HTTP requests to simple websocket-controllers
- can filter HTTP requests
- passes [Autobahn WebSocket Testsuite](https://htmlpreview.github.io/?https://github.com/timostamm/websocket-server/master/autobahn-test/reports/complete/index.html)
- does NOT implement compression
- works well with apache >= 2.4
- installable via `composer require timostamm/websocket-server`
- minimal dependencies ([react/socket](https://packagist.org/packages/react/socket), [ratchet/rfc6455](https://packagist.org/packages/ratchet/rfc6455), [guzzlehttp/psr7](https://packagist.org/packages/guzzlehttp/psr7))
- graceful shutdown via signals (or manually) 

Credits for the websocket protocol implementation go to [ratchet/rfc6455](https://github.com/ratchetphp/RFC6455).


#### Example

```php
$loop = Factory::create(); // use a react event loop

// start server
$server = new WebsocketServer($loop, [
    'uri' => '127.0.0.1:23080'
]);

// add a controller 
$server->route([
    'match' => '/example/*',
    'controller' => new class() implements ControllerInterface
    {
        function onOpen(WebSocket $connection): void
        {
            print $connection . ' connected. Sending a "Hello".' . PHP_EOL;
            $connection->send('Hello');
        }

        function onMessage(WebSocket $from, string $payload, bool $binary): void
        {
            print $from . ' sent: ' . $payload . PHP_EOL;
        }

        function onClose(WebSocket $connection): void
        {
            print $connection . ' disconnected.' . PHP_EOL;
        }

        function onError(WebSocket $connection, \Throwable $error): void
        {
            print $connection . ' error: ' . $error->getMessage() . PHP_EOL;
        }

    }
]);

// This error handler will be called when an exception was thrown
// by a filter, a controller method or the underlying tcp server.
$server->on('error', function (Throwable $error) {
    print 'Server error: ' . $error->getMessage() . PHP_EOL;
});

$loop->run(); // the react event loop processes socket connections
```


#### Routing 

This route will match paths starting with `/example/`. 
```php
$server->route([
    'match' => '/example/*',
    'controller' => $controller
]);
```
Placeholders are implemented using [fnmatch()](http://php.net/manual/en/function.fnmatch.php).

This route will match any path:
```php
$server->route([
    'controller' => $controller
]);
```

This route will deny the websocket handshake if the client did not specifiy one 
of the [subprotocols](https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API/Writing_WebSocket_servers#Miscellaneous):
```php
$server->route([
    'protocols' => ['soap'],
    'controller' => $controller
]);
```



#### Request filters
This filter responds with a HTTP 403
```php
$server->filter('example/403', function () {
    throw ResponseException::create(403);
});
```

This filter modifies the request
```php
$server->filter('example/add-attribute', function (ServerRequestInterface $request) {
    return $request->withAttribute('X-filter', 'passed');
});
```

This filter allows only the specified origins
```php
$server->filter('example/origin', new OriginFilter(['example.com']));
```

You can provide your or RequestMatcherInterface and RequestFilterInterface
implementations. Filters can also be added via `route()`: 
```php
$server->route([
    'match' => '/example/*', 
    'filter' => function(ServerRequestInterface $request){
        if ($request->getRequestTarget() === '/example/forbidden') {
            throw ResponseException::create(403);
        }
    }
    'controller' => ...
]);
```




#### Authentication

This library does not provide session integration, but provides support for 
bearer token authentication.  

Extend `AbstractTokenAuthenticator` with your token verification code and
return a user object. The user will be be available in the request attribute
"user". If no token is present, the user attribute will be empty.
 
Use `AuthorizationFilter` to check whether a user is present. Supply a 
$checkUser function to check whether the user is authorized. 

See examples/token-auth.php



#### Apache config
Add the following to a `.htaccess` file to proxy all requests with a `Upgrade: websocket` header to the websocket server.
```
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule ^(.*)$          ws://127.0.0.1:23080/$1 [P,L]
</IfModule>
```


#### Javascript

```javascript
var ws = new WebSocket("ws://localhost:23080/hello/foo");
ws.onmessage = function (event) {
    console.log("message", event.data);
};
```


#### More controller features

You can implement one or more of the following interfaces to get access to the loop, 
clients connected to this controller, etc.

```php
class MyCtrl implements ControllerInterface, ServerParamsAwareInterface, LoopAwareInterface ConnectionListAwareInterface, OnShutDownInterface, OnLastCloseInterface, OnFirstOpenInterface
{
    function setServerParams(array $serverParams): void
    {
        print 'Got server params.' . PHP_EOL;
    }

    function setLoop(\React\EventLoop\LoopInterface $loop, callable $exceptionHandler): void
    {
        print 'Got loop.' . PHP_EOL;
    }

    function setConnections(\SplObjectStorage $webSockets): void
    {
        print 'Got connection list.' . PHP_EOL;
    }
    
    function onShutDown(): PromiseInterface
    {
         // Will be called when the server is asked to shutdown.
         // Use this hook to finish important tasks, then resolve the promise.
    }

    function onLastClose(WebSocket $socket): void
    {
        print 'Last connection closed.' . PHP_EOL;
    }

    function onFirstOpen(WebSocket $socket): void
    {
        print 'First connection opened.' . PHP_EOL;
    }

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
```