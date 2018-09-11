<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 07.09.18
 * Time: 23:25
 */

namespace TS\WebSockets\Http;


use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;


interface RequestParserInterface
{

    /**
     * @param ConnectionInterface $conn
     * @return PromiseInterface <\Psr\Http\Message\ServerRequestInterface, \Throwable>
     */
    function readRequest(ConnectionInterface $conn): PromiseInterface;


}
