<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 19:05
 */

namespace TS\Websockets\Http;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;


class AllowOriginFilterTest extends TestCase
{


    /**
     * @dataProvider provideInvalidConstructorArgs
     *
     * @param string[] $allow
     */
    public function testConstructor(array $allow)
    {
        $this->expectException(\InvalidArgumentException::class);
        new AllowOriginFilter($allow);
    }


    /**
     * @dataProvider provideMatches
     *
     * @param string $origin
     * @param array $allow
     */
    public function testAllow(string $origin, ... $allow)
    {
        $subject = new AllowOriginFilter($allow);
        $request = new ServerRequest('GET', 'http://example.com', [
            'Origin' => $origin
        ]);
        $result = $subject->apply($request);
        $this->assertSame($request, $result);
    }


    /**
     * @dataProvider provideMismatches
     *
     * @param string $origin
     * @param array $allow
     */
    public function testDeny(string $origin, ... $allow)
    {
        $subject = new AllowOriginFilter($allow);
        $request = new ServerRequest('GET', 'http://example.com', [
            'Origin' => $origin
        ]);
        $this->expectException(ResponseException::class);
        $subject->apply($request);
    }


    public function testMissingHeader()
    {
        $subject = new AllowOriginFilter(['example.com']);
        $request = new ServerRequest('GET', 'http://example.com');
        $this->expectException(ResponseException::class);
        $subject->apply($request);
    }

    public function testEmptyHeader()
    {
        $subject = new AllowOriginFilter(['example.com']);
        $request = new ServerRequest('GET', 'http://example.com', [
            'Origo' => ''
        ]);
        $this->expectException(ResponseException::class);
        $subject->apply($request);
    }


    public function provideMismatches()
    {
        yield ['http://example.tv', 'example.com'];
        yield ['http://not-example.com', 'EXAMPLE.com'];
    }


    public function provideMatches()
    {
        yield ['http://example.com', 'example.com'];
        yield ['http://example.com', 'EXAMPLE.com'];
        yield ['http://EXAMPLE.com', 'example.com'];
        yield ['https://example.com', 'example.com'];
        yield ['https://example.com/', 'example.com'];
        yield ['https://example.com:8080', 'example.com'];
        yield ['http://peter:pass@example.com', 'example.com'];
    }


    public function provideInvalidConstructorArgs()
    {
        yield [[]];
        yield [[' ']];
        yield [[123]];
    }

}
