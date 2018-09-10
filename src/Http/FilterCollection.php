<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:10
 */

namespace TS\Websockets\Http;


use Psr\Http\Message\ServerRequestInterface;
use TS\Websockets\Routing\RequestMatcherInterface;
use TS\Websockets\Routing\Route;


class FilterCollection
{

    /** @var RequestFilterInterface[] */
    private $filters;


    public function __construct()
    {
        $this->filters = [];
    }


    public function add(RequestMatcherInterface $matcher, RequestFilterInterface $filter): void
    {
        $this->filters[] = [$matcher, $filter];
    }

    public function match(ServerRequestInterface $request): ?Route
    {
        foreach ($this->filters as $route) {
            if ($route->matches($request)) {
                return $route;
            }
        }
        return null;
    }


    /**
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     * @throw ResponseException
     */
    public function apply(ServerRequestInterface $request): ServerRequestInterface
    {
        foreach ($this->filters as list($matcher, $filter)) {
            /** @var RequestMatcherInterface $matcher */
            /** @var RequestFilterInterface $filter */
            if ($matcher->matches($request)) {
                $request = $filter->apply($request);
            }
        }
        return $request;
    }

}
