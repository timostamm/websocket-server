<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 20:15
 */

namespace TS\Websockets\Http;


use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ratchet\RFC6455\Handshake\NegotiatorInterface;


class WebsocketNegotiatorTest extends TestCase
{


    /** @var WebsocketNegotiator */
    private $subject;


    /** @var NegotiatorInterface | MockObject */
    private $neg;


    public function setUp()
    {
        $this->neg = $this->createMock(NegotiatorInterface::class);
        $this->subject = new WebsocketNegotiator($this->neg);
    }


    public function testSwitchingProtocols()
    {
        $request = new ServerRequest('GET', 'http//example.com');
        $response = new Response(101);

        $this->neg
            ->method('handshake')
            ->with($request)
            ->willReturn($response);

        $result = $this->subject->handshake($request, []);
        $this->assertSame($response, $result);
    }

}