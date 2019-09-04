<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 10:22
 */

namespace TS\WebSockets\Routing;


use LogicException;
use React\EventLoop\LoopInterface;
use Throwable;


class LoopAwareDelegation extends ControllerDelegation
{

    public function onInit(): void
    {
        $loop = $this->serverParams['loop'];
        if (!$loop instanceof LoopInterface) {
            $this->passError(new LogicException());
            return;
        }

        try {

            if (!$this->controller instanceof LoopAwareInterface) {
                $this->passError(new LogicException());
                return;
            }
            $this->controller->setLoop($loop, $this->errorHandler);

        } catch (Throwable $throwable) {
            $this->passMethodCallError($throwable, 'setLoop', $loop, $this->errorHandler);
        }
    }


}
