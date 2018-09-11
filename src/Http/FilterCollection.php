<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:10
 */

namespace TS\WebSockets\Http;


use Psr\Http\Message\ServerRequestInterface;
use TS\WebSockets\Routing\RequestMatcherInterface;


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
