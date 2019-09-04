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


class OnFirstOpenDelegation extends ControllerDelegation
{

    public function onOpen(WebSocket $websocket): void
    {
        if ($this->connections->count() === 1) {
            try {

                if (!$this->controller instanceof OnFirstOpenInterface) {
                    $this->passError(new LogicException());
                    return;

                }
                $this->controller->onFirstOpen($websocket);

            } catch (Throwable $throwable) {
                $this->passMethodCallError($throwable, 'onFirstOpen', $websocket);
            }
        }
    }


}
