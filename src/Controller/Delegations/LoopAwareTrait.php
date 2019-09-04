<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 10:22
 */

namespace TS\WebSockets\Controller\Delegations;


use React\EventLoop\LoopInterface;
use Throwable;


trait LoopAwareTrait
{

    /** @var LoopInterface */
    protected $loop;

    /** @var callable */
    private $loopExceptionHandler;


    public function setLoop(LoopInterface $loop, callable $exceptionHandler): void
    {
        $this->loop = $loop;
        $this->loopExceptionHandler = $exceptionHandler;
    }


    protected function handleLoopException(Throwable $throwable): void
    {
        call_user_func($this->loopExceptionHandler, $throwable);
    }


}
