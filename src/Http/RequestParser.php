<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 07.09.18
 * Time: 23:25
 */

namespace TS\WebSockets\Http;


use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use function GuzzleHttp\Psr7\parse_request;
use function GuzzleHttp\Psr7\str;


class RequestParser
{

    public const REQ_ATTR_TCP_CONNECTION = 'tcp_connection';

    protected const HTTP_HEADER_END = "\r\n\r\n";

    /** @var int */
    protected $maxHeaderSize;

    /** @var array */
    protected $serverParams;


    public function __construct(array $serverParams)
    {
        $this->serverParams = $serverParams;
        $this->maxHeaderSize = $serverParams['request_header_max_size'] ?? PHP_INT_MAX;
        if (!is_int($this->maxHeaderSize)) {
            $msg = sprintf('Invalid server parameter "request_header_max_size". Expected int, got %s.',
                gettype($this->maxHeaderSize));
            throw new \InvalidArgumentException($msg);
        }
    }


    /**
     * @param ConnectionInterface $conn
     * @return PromiseInterface <\Psr\Http\Message\ServerRequestInterface, \Throwable>
     */
    public function readRequest(ConnectionInterface $conn): PromiseInterface
    {
        $out = new Deferred();

        $buffer = '';

        $onData = function ($data) use (&$buffer, $out, $conn, &$cleanup) {
            $buffer .= $data;
            $this->guardHeaderSize($buffer);
            try {
                if ($this->isHeaderComplete($buffer)) {
                    $cleanup();
                    $request = $this->parseRequest($buffer, $conn);
                    $out->resolve($request);
                }
            } catch (\Throwable $throwable) {
                $conn->end(str(new Response(400)));
                $out->reject($throwable);
            }
        };

        $onError = function (\Throwable $error) use ($out) {
            $msg = 'Connection error while reading HTTP request data: ' . $error->getMessage();
            $out->reject(new \RuntimeException($msg, null, $error));
        };

        $onEnd = function () use ($out) {
            $msg = 'Connection prematurely ended while reading HTTP request data.';
            $out->reject(new \RuntimeException($msg));
        };

        $onClose = function () use ($out) {
            $msg = 'Connection prematurely closed while reading HTTP request data.';
            $out->reject(new \RuntimeException($msg));
        };

        $cleanup = function () use ($conn, &$onData, &$onError, &$onEnd, &$onClose) {
            $conn->removeListener('data', $onData);
            $conn->removeListener('error', $onError);
            $conn->removeListener('end', $onEnd);
            $conn->removeListener('close', $onClose);
        };
        $conn->on('data', $onData);
        $conn->once('error', $onError);
        $conn->once('end', $onEnd);
        $conn->once('close', $onClose);
        $conn->once('error', $cleanup);
        $conn->once('end', $cleanup);
        $conn->once('close', $cleanup);

        return $out->promise();
    }


    protected function isHeaderComplete(string $buffer): bool
    {
        return strpos($buffer, self::HTTP_HEADER_END) > 0;
    }


    protected function guardHeaderSize(string $buffer): void
    {
        if ($this->maxHeaderSize > 0 && strlen($buffer) > $this->maxHeaderSize) {
            throw new \OverflowException("Request header max size of {$this->maxHeaderSize} exceeded.");
        }
    }


    protected function parseRequest(string $buffer, ConnectionInterface $tcpConnection): ServerRequestInterface
    {
        $request = parse_request($buffer);
        $serverRequest = new ServerRequest(
            $request->getMethod(),
            $request->getUri(),
            $request->getHeaders(),
            $request->getBody(),
            $request->getProtocolVersion(),
            $this->serverParams
        );
        return $serverRequest
            ->withAttribute(self::REQ_ATTR_TCP_CONNECTION, $tcpConnection);
    }


}
