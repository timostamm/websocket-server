<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:55
 */

namespace TS\Websockets\Routing;


use Psr\Http\Message\ServerRequestInterface;

class UrlPatternRequestMatcher implements RequestMatcherInterface
{

    /** @var string */
    private $pattern;


    /**
     * UrlPatternRequestMatcher constructor.
     * @param string $pattern
     */
    public function __construct(string $pattern)
    {
        if (empty($pattern)) {
            throw new \InvalidArgumentException();
        }
        $this->pattern = $pattern;
    }


    /**
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }


    public function matches(ServerRequestInterface $request): bool
    {
        $target = $request->getRequestTarget();
        return fnmatch($this->getPattern(), $target);
    }


}
