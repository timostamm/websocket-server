<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 19:44
 */

namespace TS\Websockets\Http;


use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TS\Websockets\Routing\RequestMatcherInterface;

class FilterCollectionTest extends TestCase
{

    /** @var FilterCollection */
    private $subject;

    protected function setUp()
    {
        $this->subject = new FilterCollection();
    }


    public function testApplyModifiesAll()
    {
        $input = new ServerRequest('GET', 'http://example.com');

        /** @var RequestMatcherInterface | MockObject $matcher */
        $matcher = $this->createMock(RequestMatcherInterface::class);
        $matcher
            ->expects($this->exactly(2))
            ->method('matches')
            ->willReturn(true);


        /** @var RequestFilterInterface | MockObject $filter1 */
        $filter1 = $this->createMock(RequestFilterInterface::class);
        $filter1
            ->expects($this->exactly(1))
            ->method('apply')
            ->willReturnCallback(function (ServerRequestInterface $request): ServerRequestInterface {
                return $request->withAttribute('modified-by-1', 'filter-1');
            });
        $this->subject->add($matcher, $filter1);

        /** @var RequestFilterInterface | MockObject $filter2 */
        $filter2 = $this->createMock(RequestFilterInterface::class);
        $filter2
            ->expects($this->exactly(1))
            ->method('apply')
            ->willReturnCallback(function (ServerRequestInterface $request): ServerRequestInterface {
                return $request->withAttribute('modified-by-2', 'filter-2');
            });
        $this->subject->add($matcher, $filter2);


        $output = $this->subject->apply($input);
        $this->assertNotSame($input, $output);

        $this->assertSame('filter-1', $output->getAttribute('modified-by-1'));
        $this->assertSame('filter-2', $output->getAttribute('modified-by-2'));
    }


    public function testMatcherHonored()
    {
        /** @var RequestMatcherInterface | MockObject $matcher */
        $matcher = $this->getMockBuilder(RequestMatcherInterface::class)->getMock();
        $matcher->expects($this->once())
            ->method('matches')
            ->willReturn(false);

        /** @var RequestFilterInterface | MockObject $filter */
        $filter = $this->getMockBuilder(RequestFilterInterface::class)->getMock();
        $this->subject->add($matcher, $filter);

        $request = new ServerRequest('GET', 'http://example.com');
        $result = $this->subject->apply($request);
        $this->assertSame($request, $result);
    }

}