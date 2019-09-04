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


class OnErrorDelegation extends ControllerDelegation
{

    public function onError(WebSocket $websocket, Throwable $error): void
    {
        try {

            if (!$this->controller instanceof OnErrorInterface) {
                $this->passError(new LogicException());
                return;
            }

            $this->controller->onError($websocket, $error);

        } catch (Throwable $throwable) {
            $this->passMethodCallError($throwable, 'onError', $websocket);
        }
    }


}
