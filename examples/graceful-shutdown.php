<?php


use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use TS\WebSockets\ControllerInterface;
use TS\WebSockets\Routing\LoopAwareInterface;
use TS\WebSockets\Routing\OnShutDownInterface;
use TS\WebSockets\WebSocket;
use TS\WebSockets\WebSocketServer;


require_once __DIR__ . '/../vendor/autoload.php';


$loop = Factory::create();


$server = new WebSocketServer($loop, [
    'uri' => '127.0.0.1:23080',
    'shutdown_signals' => [SIGINT, SIGTERM]
]);


$server->on('error', function (Throwable $error) {
    print 'Server error: ' . $error->getMessage() . PHP_EOL;
});


$server->route([
    'controller' => new class() implements ControllerInterface, OnShutDownInterface, LoopAwareInterface
    {
        /** @var LoopInterface */
        private $loop;

        function setLoop(LoopInterface $loop, callable $exceptionHandler): void
        {
            $this->loop = $loop;
        }

        function onShutDown(): PromiseInterface
        {
            // Note: shutdown will only be called if the controller was used
            // You have to connect in order to see this message:

            print "controller shutting down..." . PHP_EOL;
            $def = new Deferred();
            $this->loop->addTimer(4, function () use ($def) {
                print "controller shut down completed." . PHP_EOL;
                $def->resolve();
            });
            return $def->promise();
        }

        function onOpen(WebSocket $socket): void
        {
            print $socket . ' connected.' . PHP_EOL;
        }

        function onMessage(WebSocket $from, string $payload, bool $binary): void
        {
            print $from . ' received: ' . $payload . PHP_EOL;
        }

        function onClose(WebSocket $socket, ?Throwable $error): void
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

