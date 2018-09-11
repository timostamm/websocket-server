<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 14:41
 */

namespace TS\Websockets\Connections;


use Evenement\EventEmitterInterface;
use TS\Websockets\ControllerInterface;
use TS\Websockets\WebSocket;


class ControllerDelegation
{

    /** @var ControllerInterface */
    private $controller;

    /** @var WebSocket */
    private $websocket;

    /** @var EventEmitterInterface */
    private $server;


    public function __construct(ControllerInterface $controller, WebSocket $websocket, EventEmitterInterface $server)
    {
        $this->controller = $controller;
        $this->websocket = $websocket;
        $this->server = $server;
        $this->attach();
    }


    protected function attach(): void
    {
        $this->websocket->on('message', [$this, 'onMessage']);
        $this->websocket->on('close', [$this, 'onClose']);
        $this->websocket->on('error', [$this, 'onError']);

        try {

            $this->controller->onOpen($this->websocket);

        } catch (\Throwable $throwable) {
            $this->server->emit('error', [$throwable]);
        }
    }


    protected function detach(): void
    {
        $this->websocket->removeListener('close', [$this, 'onClose']);
        $this->websocket->removeListener('error', [$this, 'onError']);
        $this->websocket->removeListener('message', [$this, 'onMessage']);
    }


    public function onClose(): void
    {
        try {

            $this->controller->onClose($this->websocket);

        } catch (\Throwable $throwable) {
            $this->server->emit('error', [$throwable]);
        }
        $this->detach();
    }


    public function onMessage(string $payload, bool $binary): void
    {
        try {

            $this->controller->onMessage($this->websocket, $payload, $binary);

        } catch (\Throwable $throwable) {
            $this->server->emit('error', [$throwable]);
        }
    }


    public function onError(\Throwable $error): void
    {
        try {

            $this->controller->onError($this->websocket, $error);

        } catch (\Throwable $throwable) {
            $this->server->emit('error', [$throwable]);
        }
    }


}