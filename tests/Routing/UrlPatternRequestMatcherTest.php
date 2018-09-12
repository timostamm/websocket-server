<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 19:45
 */

namespace TS\WebSockets\Routing;


use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use TS\WebSockets\Http\UrlPatternRequestMatcher;

class UrlPatternRequestMatcherTest extends TestCase
{


    /**
     * @dataProvider provideMatches
     *
     * @param string $url
     * @param string $pattern
     */
    public function testMatch(string $url, string $pattern)
    {
        $subject = new UrlPatternRequestMatcher($pattern);
        $request = new ServerRequest('GET', $url);
        $result = $subject->matches($request);
        $this->assertTrue($result);
    }

    /**
     * @dataProvider provideMismatches
     *
     * @param string $url
     * @param string $pattern
     */
    public function testMismatch(string $url, string $pattern)
    {
        $subject = new UrlPatternRequestMatcher($pattern);
        $request = new ServerRequest('GET', $url);
        $result = $subject->matches($request);
        $this->assertFalse($result);
    }


    public function provideMatches()
    {
        yield ['http://example.com/foo', '*'];
        yield ['http://example.com/foo', '/foo'];
        yield ['http://example.com/foo/', '/foo/'];
        yield ['http://example.com/foo-bar', '/foo-*'];
    }


    public function provideMismatches()
    {
        yield ['http://example.com/Foo', '/foo'];
    }


    /**
     * @dataProvider provideInvalidConstructorArgs
     *
     * @param string $pattern
     */
    public function testConstructor(string $pattern)
    {
        $this->expectException(\InvalidArgumentException::class);
        new UrlPatternRequestMatcher($pattern);
    }


    public function provideInvalidConstructorArgs()
    {
        yield [''];
    }


}