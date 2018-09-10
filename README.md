# Websocket Server

A simple nonblocking server dedicated to websockets.

- routes HTTP requests to simple websocket-controllers
- upgrades HTTP requests
- can filter HTTP requests
- works with apache >= 2.4
- minimal dependencies (react/socket, ratchet/rfc6455, guzzlehttp/psr7)


#### Example

```php
$loop = Factory::create(); // use a react event loop


// bind the server to a port
$server = new WebsocketServer($loop, [
    'uri' => '127.0.0.1:23080'
]);


// add a controller 
$server->addRoute('/hello/*', new class() implements ControllerInterface
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

$loop->run(); // the react event loop processes socket connections
```


#### Apache config
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
