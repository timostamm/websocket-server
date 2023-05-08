<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 20:15
 */

namespace TS\WebSockets\Protocol;


use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ratchet\RFC6455\Handshake\NegotiatorInterface;


class WebsocketNegotiatorTest extends TestCase
{


    /** @var WebSocketNegotiator */
    private $subject;


    /** @var NegotiatorInterface | MockObject */
    private $neg;


    public function setUp(): void
    {
        $this->neg = $this->createMock(NegotiatorInterface::class);
        $this->subject = new WebSocketNegotiator([], $this->neg);
    }


    public function testSwitchingProtocols()
    {
        $request = new ServerRequest('GET', 'http//example.com');
        $response = new Response(101, ['X-Powered-By' => 'ratchet/rfc6455']);

        $this->neg
            ->method('handshake')
            ->with($request)
            ->willReturn($response);

        $result = $this->subject->handshake($request, []);

        $this->assertSame(Message::toString($response), Message::toString($result));
    }

}
