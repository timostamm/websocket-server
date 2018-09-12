<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.09.18
 * Time: 13:58
 */

namespace TS\WebSockets\Routing;


use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use TS\WebSockets\ControllerInterface;
use TS\WebSockets\Http\MatcherFactory;
use TS\WebSockets\WebSocket;


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
        $this->expectException(\InvalidArgumentException::class);
        $this->routes->create([
            'controller' => $ctrl,
            'protocols' => 'str'
        ]);
    }

    public function testProtocolsProvided()
    {
        $ctrl = $this->createMock(ControllerInterface::class);
        $route = $this->routes->create([
            'protocols' => ['abc', 'xyz'],
            'controller' => $ctrl
        ]);
        $this->assertCount(2, $route->getSupportedSubProtocols());
    }


    public function testControllerMissing()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->routes->create([]);
    }


    public function testControllerNull()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->routes->create([
            'controller' => null
        ]);
    }

    public function testControllerInstance()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not implement');
        $this->routes->create([
            'controller' => $this
        ]);
    }

    public function testControllerType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('got int');
        $this->routes->create([
            'controller' => 123
        ]);
    }

    public function testControllerAndOnX()
    {
        $ctrl = $this->createMock(ControllerInterface::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot provide option');
        $this->routes->create([
            'controller' => $ctrl,
            'on_open' => function () {
            }
        ]);
    }


    public function testOnX()
    {
        $calls = [];
        $route = $this->routes->create([
            'on_open' => function () use (&$calls) {
                $calls[] = 'on_open';
            },
            'on_close' => function () use (&$calls) {
                $calls[] = 'on_close';
            },
            'on_message' => function () use (&$calls) {
                $calls[] = 'on_message';
            },
            'on_error' => function () use (&$calls) {
                $calls[] = 'on_error';
            }
        ]);

        $ctrl = $route->getController();

        /** @var WebSocket $ws */
        $ws = $this->createMock(WebSocket::class);

        $ctrl->onOpen($ws);
        $ctrl->onClose($ws);
        $ctrl->onMessage($ws, 'xx', false);
        $ctrl->onError($ws, new \Exception());

        $this->assertSame(['on_open', 'on_close', 'on_message', 'on_error'], $calls);

    }


}