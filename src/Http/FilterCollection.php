<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:10
 */

namespace TS\WebSockets\Http;


use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;


class FilterCollection
{

    /** @var RequestFilterInterface[] */
    private $filters;

    protected $serverParams;
    protected $matcherFactory;


    public function __construct(array $serverParams)
    {
        $this->filters = [];
        $this->serverParams = $serverParams;
    }


    final public function add(RequestMatcherInterface $matcher, RequestFilterInterface $filter): void
    {
        $this->filters[] = [$matcher, $filter];
    }


    /**
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     * @throw ResponseException
     */
    final public function apply(ServerRequestInterface $request): ServerRequestInterface
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


    public function create($filter): RequestFilterInterface
    {
        if (is_callable($filter)) {
            $filter = new CallbackRequestFilter($filter);
        } else if (!$filter instanceof RequestFilterInterface) {
            throw new InvalidArgumentException('Invalid argument $filter. Expected callable or RequestFilterInterface.');
        }
        return $filter;
    }


}
