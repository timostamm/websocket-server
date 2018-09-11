<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 15:45
 */

namespace TS\Websockets\Http;


use Psr\Http\Message\ServerRequestInterface;


class OriginFilter implements RequestFilterInterface
{

    private $allowedOrigins;

    /**
     * @param string[] $allowedOrigins
     */
    public function __construct(array $allowedOrigins)
    {
        if (empty($allowedOrigins)) {
            throw new \InvalidArgumentException('No allowed origins provides');
        }
        foreach ($allowedOrigins as $origin) {
            if (!is_string($origin)) {
                throw new \InvalidArgumentException();
            }
            $origin = trim($origin);
            if (empty($origin)) {
                throw new \InvalidArgumentException();
            }
            $this->allowedOrigins[] = strtolower($origin);
        }
    }


    public function apply(ServerRequestInterface $request): ServerRequestInterface
    {
        $origin = $request->getHeaderLine('Origin');
        if (empty($origin)) {
            $msg = 'Origin not provided';
            throw ResponseException::create(403, $msg, $msg);
        }
        $host = strtolower(parse_url($origin, PHP_URL_HOST));
        if (!in_array($host, $this->allowedOrigins)) {
            $msg = 'Origin is not allowed';
            throw ResponseException::create(403, $msg, $msg);
        }
        return $request;
    }


}