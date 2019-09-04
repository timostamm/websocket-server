<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 10:22
 */

namespace TS\WebSockets\Controller\Delegations;


use LogicException;
use Throwable;
use TS\WebSockets\Controller\ControllerDelegation;
use TS\WebSockets\WebSocket;


class OnLastCloseDelegation extends ControllerDelegation
{

    public function onClose(WebSocket $websocket, ?Throwable $error): void
    {
        if ($this->connections->count() === 1) {
            try {

                if (!$this->controller instanceof OnLastCloseInterface) {
                    $this->passError(new LogicException());
                    return;
                }

                $this->controller->onLastClose($websocket);

            } catch (Throwable $throwable) {
                $this->passMethodCallError($throwable, 'onLastClose', $websocket);
            }
        }
    }

}
