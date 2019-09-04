<?php

namespace TS\WebSockets;

use Ratchet\RFC6455\Messaging\Frame;
use Throwable;

class WebsocketController implements ControllerInterface
{


    function onOpen(WebSocket $socket): void
    {
    }


    function onMessage(WebSocket $from, string $payload, bool $binary): void
    {
        $from->close(Frame::CLOSE_BAD_PAYLOAD, 'Sorry, do not understand you.');
    }


    function onClose(WebSocket $socket, ?Throwable $error): void
    {
    }


}
