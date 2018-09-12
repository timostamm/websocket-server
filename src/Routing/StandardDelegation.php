<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 10:22
 */

namespace TS\WebSockets\Routing;


use TS\WebSockets\WebSocket;


class StandardDelegation extends ControllerDelegation
{

    public function onOpen(WebSocket $websocket): void
    {
        try {

            $this->controller->onOpen($websocket);

        } catch (\Throwable $throwable) {
            $this->passMethodCallError($throwable, 'onOpen', $websocket);
        }
    }

    public function onClose(WebSocket $websocket): void
    {
        try {

            $this->controller->onClose($websocket);

        } catch (\Throwable $throwable) {
            $this->passMethodCallError($throwable, 'onClose', $websocket);
        }
    }

    public function onMessage(WebSocket $websocket, string $payload, bool $binary): void
    {
        try {

            $this->controller->onMessage($websocket, $payload, $binary);

        } catch (\Throwable $throwable) {
            $this->passMethodCallError($throwable, 'onOpen', $websocket);
        }
    }

    public function onError(WebSocket $websocket, \Throwable $error): void
    {
        try {

            $this->controller->onError($websocket, $error);

        } catch (\Throwable $throwable) {
            $this->passMethodCallError($throwable, 'onError', $websocket);
        }
    }


}