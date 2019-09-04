<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 23:55
 */

namespace TS\WebSockets\Controller\Delegations;


use React\Promise\PromiseInterface;


/**
 *
 * Will be called when the server is asked to shutdown.
 *
 * Use this hook to finish important tasks, then resolve the promise.
 *
 * Your WebSocket connections will automatically be closed *after*
 * your shutdown is complete.
 *
 * Note: shutdown will only be called if the controller was used.
 *
 */
interface OnShutDownInterface
{

    function onShutDown(): PromiseInterface;

}
