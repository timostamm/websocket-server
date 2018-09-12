<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 09:03
 */

namespace TS\WebSockets\Http;


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

    private $xPoweredBy;


    public function __construct(array $serverParams, NegotiatorInterface $handshakeNegotiator = null)
    {
        $this->negotiator = $handshakeNegotiator
            ?? new ServerNegotiator(new RequestVerifier());

        $strict = $serverParams['strict_sub_protocol_check'] ?? true;
        $this->negotiator->setStrictSubProtocolCheck($strict);

        $this->xPoweredBy = $serverParams['X-Powered-By'] ?? 'ratchet/rfc6455';
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
        $response = $this->negotiator->handshake($request)
            ->withHeader('X-Powered-By', $this->xPoweredBy);
        if ($response->getStatusCode() !== self::HTTP_SWITCHING_PROTOCOLS) {
            throw new ResponseException($response);
        }
        return $response;
    }


}
