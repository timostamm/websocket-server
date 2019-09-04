<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 13:58
 */

namespace TS\WebSockets\Controller;


use GuzzleHttp\Psr7\ServerRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TS\WebSockets\Http\MatcherFactory;
use TS\WebSockets\Http\RequestMatcherInterface;


class RouteCreationByOptionsTest extends TestCase
{

    /** @var RouteCollection */
    private $routes;

    protected function setUp()
    {
        $this->routes = new RouteCollection([], new MatcherFactory([]));
    }

    public function testMatchDefault()
    {
        $ctrl = $this->createMock(ControllerInterface::class);
        $route = $this->routes->create([
            'controller' => $ctrl
        ]);
        $matches = $route->matches(new ServerRequest('GET', 'any'));
        $this->assertTrue($matches);
    }

    public function testMatchPattern()
    {
        $ctrl = $this->createMock(ControllerInterface::class);
        $route = $this->routes->create([
            'controller' => $ctrl,
            'match' => '/foo/*'
        ]);
        $this->assertTrue($route->matches(new ServerRequest('GET', '/foo/abc')));
        $this->assertTrue($route->matches(new ServerRequest('GET', '/foo/xyz')));
        $this->assertFalse($route->matches(new ServerRequest('GET', '/bar')));
    }


    public function testMatchMatcher()
    {
        $matcher = $this->createMock(RequestMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('matches')
            ->willReturn(true);
        $ctrl = $this->createMock(ControllerInterface::class);
        $route = $this->routes->create([
            'controller' => $ctrl,
            'match' => $matcher
        ]);
        $this->assertTrue($route->matches(new ServerRequest('GET', '/foo/abc')));
    }


    public function testProtocolsDefault()
    {
        $ctrl = $this->createMock(ControllerInterface::class);
        $route = $this->routes->create([
            'controller' => $ctrl
        ]);
        $this->assertCount(0, $route->getSupportedSubProtocols());
    }

    public function testProtocolsType()
    {
        $ctrl = $this->createMock(ControllerInterface::class);
        $this->expectException(InvalidArgumentException::class);
        $this->routes->create([
            'controller' => $ctrl,
            'sub_protocols' => 'str'
        ]);
    }

    public function testProtocolsProvided()
    {
        $ctrl = $this->createMock(ControllerInterface::class);
        $route = $this->routes->create([
            'sub_protocols' => ['abc', 'xyz'],
            'controller' => $ctrl
        ]);
        $this->assertCount(2, $route->getSupportedSubProtocols());
    }


    public function testControllerMissing()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->routes->create([]);
    }


    public function testControllerNull()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->routes->create([
            'controller' => null
        ]);
    }

    public function testControllerInstance()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not implement');
        $this->routes->create([
            'controller' => $this
        ]);
    }

    public function testControllerType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('got int');
        $this->routes->create([
            'controller' => 123
        ]);
    }

}
