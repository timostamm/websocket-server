<?php

use TS\WebSockets\Controller\ControllerInterface;
use TS\WebSockets\WebSocket;

/**
 * Sends "Hello" with the current user name
 */
class HelloUserController implements ControllerInterface
{

    function onOpen(WebSocket $socket): void
    {
        $user = $socket->getRequest()->getAttribute('user');
        $username = $user ?? 'anonymous user';
        $hello = sprintf('Hello %s, welcome to %s.', $username, $socket->getRequest()->getRequestTarget());
        $socket->send($hello);
    }

    function onMessage(WebSocket $from, string $payload, bool $binary): void
    {
    }

    function onClose(WebSocket $socket, ?Throwable $error): void
    {
    }


}
