<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 24.09.18
 * Time: 10:32
 */

namespace TS\WebSockets\Http;


use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;


class RouteStarsFilters implements RequestFilterInterface
{

    /** @var string */
    private $pattern;

    /** @var int */
    private $starCount;

    /** @var string */
    private $re;


    /**
     * UrlPatternRequestMatcher constructor.
     * @param string $pattern
     */
    public function __construct(string $pattern)
    {
        if (empty($pattern)) {
            throw new InvalidArgumentException();
        }
        $re = preg_quote($pattern, '/');
        $re = str_replace('\*', '([^\/]+)', $re);
        $this->re = sprintf('/^%s$/', $re);
        $this->pattern = $pattern;
        $this->starCount = substr_count($pattern, '*');
    }


    public function apply(ServerRequestInterface $request): ServerRequestInterface
    {
        $values = array_fill(0, $this->getStarCount(), '');

        $target = $request->getRequestTarget();
        if ( !fnmatch($this->getPattern(), $target)) {
            return $request->withAttribute('route_stars', $values);
        }

        $ok = preg_match($this->getRegEx(), $request->getRequestTarget(), $matches);
        if ($ok === 0) {
            return $request->withAttribute('route_stars', $values);
        }

        foreach ($values as $i => $v) {
            $values[$i] = $matches[$i + 1];
        }
        return $request->withAttribute('route_stars', $values);
    }


    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getStarCount(): int
    {
        return $this->starCount;
    }

    public function getRegEx(): string
    {
        return $this->re;
    }


}
