<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 15:35
 */

namespace TS\WebSockets\Http;


use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;


class ResponseException extends RuntimeException
{


    public static function create(int $status, string $body = null, string $message = null): self
    {
        $response = new Response($status, [], $body);
        return new self($response, $message);
    }

    private $response;


    public function __construct(ResponseInterface $response, string $message = null, Throwable $previous = null)
    {
        if (is_null($message)) {
            $message = 'HTTP ' . $response->getStatusCode();
            if (!empty($response->getReasonPhrase())) {
                $message .= ' ' . $response->getReasonPhrase();
            }
        }
        parent::__construct($message, $response->getStatusCode(), $previous);
        $this->response = $response;
    }


    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

}
