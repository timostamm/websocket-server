<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:54
 */

namespace TS\WebSockets\Http;


use Psr\Http\Message\ServerRequestInterface;

interface RequestMatcherInterface
{

    function matches(ServerRequestInterface $request): bool;

}
