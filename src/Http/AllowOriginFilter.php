<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 15:45
 */

namespace Http;


use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ServerRequestInterface;
use TS\Websockets\Http\ResponseException;


class AllowOriginFilter implements RequestFilterInterface
{

    private $allowedOrigins;

    /**
     * @param string[] $allowedOrigins
     */
    public function __construct(array $allowedOrigins)
    {
        $this->allowedOrigins = $allowedOrigins;
    }


    public function apply(ServerRequestInterface $request): ServerRequestInterface
    {
        $origin = new Uri($request->getHeaderLine('Origin'));
        $host = $origin->getHost();
        if (!in_array($host, $this->allowedOrigins)) {
            $msg = 'Origin is not allowed';
            throw ResponseException::create(403, $msg, $msg);
        }
        return $request;
    }


}