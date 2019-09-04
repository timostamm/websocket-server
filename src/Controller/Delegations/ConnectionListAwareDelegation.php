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

class ConnectionListAwareDelegation extends ControllerDelegation
{

    public function onInit(): void
    {
        try {

            if (!$this->controller instanceof ConnectionListAwareInterface) {
                $this->passError(new LogicException());
                return;
            }

            $this->controller->setConnections($this->connections);

        } catch (Throwable $throwable) {
            $this->passMethodCallError($throwable, 'setConnections', $this->connections);
        }
    }


}
