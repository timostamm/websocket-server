<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 10:22
 */

namespace TS\WebSockets\Routing;


use React\Promise\PromiseInterface;
use function React\Promise\resolve;


class OnShutDownDelegation extends ControllerDelegation
{

    public function onShutdown(): PromiseInterface
    {
        try {

            if (!$this->controller instanceof OnShutDownInterface) {
                $this->passError(new \LogicException());
                return resolve();

            }
            return $this->controller->onShutdown();

        } catch (\Throwable $throwable) {
            $this->passMethodCallError($throwable, 'onShutdown');
            return resolve();
        }
    }


}