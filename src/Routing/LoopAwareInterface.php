<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 23:55
 */

namespace TS\WebSockets\Routing;


use React\EventLoop\LoopInterface;


/**
 *
 * Implement this interface to get access to the loop
 * in your controller.
 *
 * If you use loop timers, your timers must not throw
 * exceptions.
 *
 * If necessary, use the provide exceptionHandler to
 * pass exceptions to the server.
 *
 */
interface LoopAwareInterface
{

    function setLoop(LoopInterface $loop, callable $exceptionHandler): void;

}
