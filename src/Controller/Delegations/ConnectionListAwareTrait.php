<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 10:22
 */

namespace TS\WebSockets\Controller\Delegations;


use SplObjectStorage;
use TS\WebSockets\WebSocket;


trait ConnectionListAwareTrait
{

    /** @var SplObjectStorage|WebSocket[] */
    protected $connections;


    function setConnections(SplObjectStorage $webSockets): void
    {
        $this->connections = $webSockets;
    }


}
