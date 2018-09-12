<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 10:22
 */

namespace TS\WebSockets\Routing;


class ServerParamsAwareDelegation extends ControllerDelegation
{

    public function onInit(): void
    {
        try {

            if (!$this->controller instanceof ServerParamsAwareInterface) {
                $this->passError(new \LogicException());
                return;

            }
            $this->controller->setServerParams($this->serverParams);

        } catch (\Throwable $throwable) {
            $this->passMethodCallError($throwable, 'setServerParams', $this->serverParams);
        }
    }


}