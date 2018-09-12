<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 10:22
 */

namespace TS\WebSockets\Routing;


use TS\WebSockets\WebSocket;


class OnLastCloseDelegation extends ControllerDelegation
{

    public function onClose(WebSocket $websocket): void
    {
        if ($this->connections->count() === 1) {
            try {

                if (!$this->controller instanceof OnLastCloseInterface) {
                    $this->passError(new \LogicException());
                    return;
                }

                $this->controller->onLastClose($websocket);

            } catch (\Throwable $throwable) {
                $this->passMethodCallError($throwable, 'onLastClose', $websocket);
            }
        }
    }

}