<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:10
 */

namespace TS\WebSockets\Routing;


use Psr\Http\Message\ServerRequestInterface;

class RouteCollection
{

    /** @var Route[] */
    private $routes;


    public function __construct()
    {
        $this->routes = [];
    }


    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }


    public function match(ServerRequestInterface $request): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                return $route;
            }
        }
        return null;
    }

}
