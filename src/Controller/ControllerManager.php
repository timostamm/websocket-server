<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 09:59
 */

namespace TS\WebSockets\Controller;


use React\Promise\PromiseInterface;
use SplObjectStorage;
use Throwable;
use TS\WebSockets\Controller\Delegations\ConnectionListAwareDelegation;
use TS\WebSockets\Controller\Delegations\ConnectionListAwareInterface;
use TS\WebSockets\Controller\Delegations\LoopAwareDelegation;
use TS\WebSockets\Controller\Delegations\LoopAwareInterface;
use TS\WebSockets\Controller\Delegations\OnErrorDelegation;
use TS\WebSockets\Controller\Delegations\OnErrorInterface;
use TS\WebSockets\Controller\Delegations\OnFirstOpenDelegation;
use TS\WebSockets\Controller\Delegations\OnFirstOpenInterface;
use TS\WebSockets\Controller\Delegations\OnLastCloseDelegation;
use TS\WebSockets\Controller\Delegations\OnLastCloseInterface;
use TS\WebSockets\Controller\Delegations\OnShutDownDelegation;
use TS\WebSockets\Controller\Delegations\OnShutDownInterface;
use TS\WebSockets\Controller\Delegations\StandardDelegation;
use TS\WebSockets\WebSocket;
use function React\Promise\all;


class ControllerManager
{

    protected $serverParams;
    protected $serverErrorHandler;

    /**
     * SplObjectStorage<ControllerInterface<ControllerDelegation>>
     *
     * @var SplObjectStorage
     */
    protected $delByCtrl;

    /**
     * SplObjectStorage<ControllerInterface<SplObjectStorage<WebSocket>>>
     *
     * @var SplObjectStorage | SplObjectStorage[]
     */
    protected $wsByCtrl;


    /** @var bool */
    private $shuttingDown = false;


    public function __construct(array $serverParams, callable $serverErrorHandler)
    {
        $this->serverParams = $serverParams;
        $this->serverErrorHandler = $serverErrorHandler;
        $this->delByCtrl = new SplObjectStorage();
        $this->wsByCtrl = new SplObjectStorage();
    }


    public function add(ControllerInterface $controller, WebSocket $webSocket): void
    {
        if ($this->shuttingDown || $webSocket->isClosed()) {
            return;
        }

        $connections = $this->getConnectionsByCtrl($controller);

        /** @var ControllerDelegation[] $delegations */
        $delegations = $this->getDelegationsByCtrl($controller, $initial);

        if ($initial) {
            foreach ($delegations as $delegation) {
                /** @var ControllerDelegation $delegation */
                $delegation->onInit();
            }
        }

        $connections->attach($webSocket);

        foreach ($delegations as $delegation) {
            /** @var ControllerDelegation $delegation */
            $delegation->onOpen($webSocket);
        }

        $webSocket->on('error', function (Throwable $error) use ($webSocket, $connections, $delegations) {
            foreach ($delegations as $delegation) {
                $delegation->onError($webSocket, $error);
            }
        });

        $webSocket->on('message', function (string $payload, bool $binary) use ($webSocket, $connections, $delegations) {
            foreach ($delegations as $delegation) {
                $delegation->onMessage($webSocket, $payload, $binary);
            }
        });

        $webSocket->once('close', function (?Throwable $error) use ($webSocket, $connections, $delegations) {
            foreach ($delegations as $delegation) {
                $delegation->onClose($webSocket, $error);
            }
            $connections->detach($webSocket);
        });

    }


    /**
     * Shutdown all controllers.
     *
     * @return PromiseInterface
     */
    public function shutDown(): PromiseInterface
    {
        $this->shuttingDown = true;
        $ps = [];
        foreach ($this->delByCtrl as $controller) {
            foreach ($this->delByCtrl[$controller] as $delegation) {
                /** @var ControllerDelegation $delegation */
                $ps[] = $delegation->onShutdown();
            }
        }
        return all($ps);
    }


    public function stats(): array
    {
        $a = [
            'controller instances' => count($this->getControllers())
        ];
        foreach ($this->getControllers() as $controller) {
            $k = 'controller ' . get_class($controller);
            $a[$k] = $this->getConnectionsByCtrl($controller)->count() . ' clients';
        }
        return $a;
    }


    /**
     * @return ControllerInterface[]
     */
    protected function getControllers(): array
    {
        $controllers = [];
        foreach ($this->wsByCtrl as $item) {
            $controllers[] = $item;
        }
        return $controllers;
    }


    protected function getConnectionsByCtrl(ControllerInterface $controller): SplObjectStorage
    {
        if (!$this->wsByCtrl->contains($controller)) {
            $this->wsByCtrl[$controller] = new SplObjectStorage();
        }
        return $this->wsByCtrl[$controller];
    }


    protected function getDelegationsByCtrl(ControllerInterface $controller, ?bool &$initial): SplObjectStorage
    {
        if ($this->delByCtrl->contains($controller)) {
            $initial = false;
        } else {
            $initial = true;
            $errorHandler = function (Throwable $error) use ($controller) {
                $controllerEx = $error instanceof ControllerException
                    ? $error
                    : ControllerException::controller($controller, $error);
                $fn = $this->serverErrorHandler;
                $fn($controllerEx);
            };
            $list = new SplObjectStorage();
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

        if ($controller instanceof OnShutDownInterface) {
            $a[] = new OnShutDownDelegation($this->serverParams, $controller, $this->getConnectionsByCtrl($controller), $errorHandler);
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

        if ($controller instanceof OnErrorInterface) {
            $a[] = new OnErrorDelegation($this->serverParams, $controller, $this->getConnectionsByCtrl($controller), $errorHandler);
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
