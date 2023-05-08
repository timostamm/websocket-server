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
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

class CallbackRequestFilterTest extends TestCase
{

    /** @var ServerRequestInterface */
    private $request;

    protected function setUp(): void
    {
        $this->request = new ServerRequest('GET', 'some');
    }

    public function testBadReturn()
    {
        $cb = new CallbackRequestFilter(function () {
            return 'abc';
        });

        $this->expectException(UnexpectedValueException::class);
        $cb->apply($this->request);
    }


    public function testReturnNull()
    {
        $cb = new CallbackRequestFilter(function () {
            return null;
        });
        $ret = $cb->apply($this->request);
        $this->assertSame($ret, $this->request);
    }

}
