<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:10
 */

namespace TS\Websockets\Routing;


use Psr\Http\Message\ServerRequestInterface;
use TS\Websockets\ControllerInterface;


class Route
{

    private $requestMatcher;
    private $subProtocols;
    private $controller;


    public function __construct(RequestMatcherInterface $requestMatcher, ControllerInterface $controller, array $subProtocols)
    {
        $this->requestMatcher = $requestMatcher;
        $this->controller = $controller;
        $this->subProtocols = $subProtocols;
    }


    public function matches(ServerRequestInterface $request): bool
    {
        return $this->requestMatcher->matches($request);
    }


    public function getController(): ControllerInterface
    {
        return $this->controller;
    }


    public function getSupportedSubProtocols(): array
    {
        return $this->subProtocols;
    }


}
