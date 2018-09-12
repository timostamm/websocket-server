<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:10
 */

namespace TS\WebSockets\Http;


class MatcherFactory
{

    protected $serverParams;


    public function __construct(array $serverParams)
    {
        $this->serverParams = $serverParams;
    }

    public function create($match): RequestMatcherInterface
    {
        if (is_string($match)) {
            $match = new UrlPatternRequestMatcher($match);
        } else if (!$match instanceof RequestMatcherInterface) {
            throw new \InvalidArgumentException('Invalid argument $match. Expected string or RequestMatcherInterface.');
        }
        return $match;
    }


}
