<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 14:45
 */

namespace TS\WebSockets\Http;


use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class RouteStarsFilterTest extends TestCase
{


    public function testConstructor()
    {
        $f = new RouteStarsFilters('/api-v1/a/*/b');
        $this->assertSame('/api-v1/a/*/b', $f->getPattern());
        $this->assertSame('/^\/api\-v1\/a\/([^\/]+)\/b$/', $f->getRegEx());
        $this->assertSame(1, $f->getStarCount());
    }


    public function testNoPatternMatch()
    {
        $f = new RouteStarsFilters('/api-v1/a/*/b');
        $r = $f->apply(new ServerRequest('GET', 'http://localhost/api-v2/a/123/b'));
        $s = $r->getAttribute('route_stars');
        $this->assertInternalType('array', $s);
        $this->assertEquals([''], $s);
    }


    public function testPatternMatch()
    {
        $f = new RouteStarsFilters('/api-v1/a/*/b');
        $r = $f->apply(new ServerRequest('GET', 'http://localhost/api-v1/a/123/b'));
        $s = $r->getAttribute('route_stars');
        $this->assertEquals(['123'], $s);
    }

    public function testPatternEnd()
    {
        $f = new RouteStarsFilters('/api-v1/a/*/b');
        $r = $f->apply(new ServerRequest('GET', 'http://localhost/api-v1/a/x/b/api-v1/a/y/b'));
        $s = $r->getAttribute('route_stars');
        $this->assertEquals([''], $s);
    }


}