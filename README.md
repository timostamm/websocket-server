# Websocket Server

A simple nonblocking server dedicated to websockets.


#### Example

```php
$loop = Factory::create();

$server = new WebsocketServer($loop, [
    'uri' => '127.0.0.1:23080'
]);


// add a controller that handles websocket connections 
// for a specific route 
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

$loop->run();
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
