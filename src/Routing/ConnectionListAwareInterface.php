<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 23:55
 */

namespace TS\WebSockets\Routing;


interface ConnectionListAwareInterface
{

    function setConnections(\SplObjectStorage $webSockets): void;

}
