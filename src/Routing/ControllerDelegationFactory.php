<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 09:59
 */

namespace TS\WebSockets\Routing;


use TS\WebSockets\ControllerInterface;
use TS\WebSockets\WebSocket;


class ControllerDelegationFactory
{

    protected $serverParams;
    protected $serverErrorHandler;

    /**
     * SplObjectStorage<ControllerInterface<ControllerDelegation>>
     *
     * @var \SplObjectStorage
     */
    protected $delByCtrl;

    /**
     * SplObjectStorage<ControllerInterface<SplObjectStorage<WebSocket>>>
     *
     * @var \SplObjectStorage | \SplObjectStorage[]
     */
    protected $wsByCtrl;


    public function __construct(array $serverParams, callable $serverErrorHandler)
    {
        $this->serverParams = $serverParams;
        $this->serverErrorHandler = $serverErrorHandler;
        $this->delByCtrl = new \SplObjectStorage();
        $this->wsByCtrl = new \SplObjectStorage();
    }


    public function add(ControllerInterface $controller, WebSocket $webSocket): void
    {
        $connections = $this->getConnectionsByCtrl($controller);

        /** @var ControllerDelegation[] $delegations */
        $delegations = $this->getDelegationsByCtrl($controller);

        $connections->attach($webSocket);

        foreach ($delegations as $delegation) {
            /** @var ControllerDelegation $delegation */
            $delegation->onInit();
        }

        foreach ($delegations as $delegation) {
            /** @var ControllerDelegation $delegation */
            $delegation->onOpen($webSocket);
        }

        $webSocket->on('error', function (\Throwable $error) use ($webSocket, $connections, $delegations) {
            foreach ($delegations as $delegation) {
                $delegation->onError($webSocket, $error);
            }
        });

        $webSocket->on('message', function (string $payload, bool $binary) use ($webSocket, $connections, $delegations) {
            foreach ($delegations as $delegation) {
                $delegation->onMessage($webSocket, $payload, $binary);
            }
        });

        $webSocket->once('close', function () use ($webSocket, $connections, $delegations) {
            foreach ($delegations as $delegation) {
                $delegation->onClose($webSocket);
            }
            $connections->detach($webSocket);
        });

    }


    protected function getConnectionsByCtrl(ControllerInterface $controller): \SplObjectStorage
    {
        if (!$this->wsByCtrl->contains($controller)) {
            $this->wsByCtrl[$controller] = new \SplObjectStorage();
        }
        return $this->wsByCtrl[$controller];
    }


    protected function getDelegationsByCtrl(ControllerInterface $controller): \SplObjectStorage
    {
        if (!$this->delByCtrl->contains($controller)) {
            $errorHandler = function (\Throwable $error) use ($controller) {
                $wrapped = ControllerException::controller($controller, $error);
                $fn = $this->serverErrorHandler;
                $fn($wrapped);
            };
            $list = new \SplObjectStorage();
            foreach ($this->createDelegations($controller, $errorHandler) as $item) {
                $list->attach($item);
            }
            $this->delByCtrl->attach($controller, $list);
        }
        return $this->delByCtrl[$controller];
    }


    /**
     * @param ControllerInterface $controller
     * @param callable $errorHandler
     * @return ControllerDelegation[]
     */
    protected function createDelegations(ControllerInterface $controller, callable $errorHandler): array
    {
        $a = [];

        if ($controller instanceof ServerParamsAwareInterface) {
            $a[] = new ServerParamsAwareDelegation($this->serverParams, $controller, $this->getConnectionsByCtrl($controller), $errorHandler);
        }

        if ($controller instanceof LoopAwareInterface) {
            $a[] = new LoopAwareDelegation($this->serverParams, $controller, $this->getConnectionsByCtrl($controller), $errorHandler);
        }

        if ($controller instanceof ConnectionListAwareInterface) {
            $a[] = new ConnectionListAwareDelegation($this->serverParams, $controller, $this->getConnectionsByCtrl($controller), $errorHandler);
        }

        if ($controller instanceof OnFirstOpenInterface) {
            $a[] = new OnFirstOpenDelegation($this->serverParams, $controller, $this->getConnectionsByCtrl($controller), $errorHandler);
        }

        if ($controller instanceof ControllerInterface) {
            $a[] = new StandardDelegation($this->serverParams, $controller, $this->getConnectionsByCtrl($controller), $errorHandler);
        }

        if ($controller instanceof OnLastCloseInterface) {
            $a[] = new OnLastCloseDelegation($this->serverParams, $controller, $this->getConnectionsByCtrl($controller), $errorHandler);
        }

        return $a;
    }


}