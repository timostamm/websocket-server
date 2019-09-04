<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 23:55
 */

namespace TS\WebSockets\Routing;


use Throwable;
use TS\WebSockets\WebSocket;


/**
 * Implement this interface in your controller to receive
 * errors.
 *
 * Note that you should NOT close the socket connection,
 * as it is closed automatically.
 *
 * Also note that you will also receive all errors in the
 * standard onClose callback.
 *
 * Interface OnErrorInterface
 * @package TS\WebSockets\Routing
 */
interface OnErrorInterface
{

    function onError(WebSocket $socket, Throwable $error): void;

}
