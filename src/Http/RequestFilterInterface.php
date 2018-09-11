<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 15:38
 */

namespace TS\WebSockets\Http;


use Psr\Http\Message\ServerRequestInterface;

interface RequestFilterInterface
{


    /**
     * Return modified request or throw an exception to
     * provide a response.
     *
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     * @throw ResponseException
     */
    function apply(ServerRequestInterface $request): ServerRequestInterface;


}