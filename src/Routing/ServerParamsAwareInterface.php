<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 23:55
 */

namespace TS\WebSockets\Routing;


interface ServerParamsAwareInterface
{

    function setServerParams(array $serverParams): void;

}
