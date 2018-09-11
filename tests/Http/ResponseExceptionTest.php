<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 19:34
 */

namespace TS\WebSockets\Http;


use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ResponseExceptionTest extends TestCase
{

    /** @var ResponseInterface */
    private $response;


    protected function setUp()
    {
        $this->response = new Response(404, [], 'body', '1.1', 'really not found');
    }

    public function testGetCode()
    {
        $ex = new ResponseException($this->response);
        $this->assertSame(404, $ex->getCode());
    }


    public function testGetResponse()
    {
        $ex = new ResponseException($this->response);
        $this->assertSame($this->response, $ex->getResponse());
    }


    public function testExceptionMessageFirst()
    {
        $ex = new ResponseException($this->response, 'custom-message');
        $this->assertSame('custom-message', $ex->getMessage());
    }


    public function testResponseReasonPhrase()
    {
        $ex = new ResponseException($this->response);
        $this->assertSame('HTTP 404 really not found', $ex->getMessage());
    }


}