<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 09:03
 */

namespace TS\Websockets\Http;


use Http\ResponseException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Handshake\NegotiatorInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;

class WebsocketNegotiator
{

    const HTTP_SWITCHING_PROTOCOLS = 101;

    /** @var NegotiatorInterface */
    protected $negotiator;


    public function __construct(NegotiatorInterface $handshakeNegotiator = null)
    {
        $this->negotiator = $handshakeNegotiator
            ?? new ServerNegotiator(new RequestVerifier());

        $this->negotiator->setStrictSubProtocolCheck(true);
    }


    /**
     * @param ServerRequestInterface $request
     * @param array $subProtocols
     * @return ResponseInterface
     * @throws ResponseException
     */
    public function handshake(ServerRequestInterface $request, array $subProtocols): ResponseInterface
    {
        $this->negotiator->setSupportedSubProtocols($subProtocols);
        $response = $this->negotiator->handshake($request);
        if ($response->getStatusCode() !== self::HTTP_SWITCHING_PROTOCOLS) {
            throw new ResponseException($response);
        }
        return $response;
    }


}
