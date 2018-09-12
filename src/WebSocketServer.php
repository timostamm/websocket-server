<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 07.09.18
 * Time: 17:17
 */

namespace TS\WebSockets;


use Evenement\EventEmitter;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use React\Socket\TcpServer;
use TS\WebSockets\Connections\ControllerDelegation;
use TS\WebSockets\Connections\HandlerFactory;
use TS\WebSockets\Http\FilterCollection;
use TS\WebSockets\Http\MatcherFactory;
use TS\WebSockets\Http\RequestFilterInterface;
use TS\WebSockets\Http\RequestParser;
use TS\WebSockets\Http\RequestParserInterface;
use TS\WebSockets\Http\ResponseException;
use TS\WebSockets\Http\WebsocketNegotiator;
use TS\WebSockets\Routing\RequestMatcherInterface;
use TS\WebSockets\Routing\Route;
use TS\WebSockets\Routing\RouteCollection;
use function GuzzleHttp\Psr7\str;


/**
 *
 * Emits:
 *
 * "connection" => Websocket $connection
 *
 * "error" => \Throwable $throwable
 *
 */
class WebSocketServer extends EventEmitter implements ServerInterface
{

    const DEFAULT_SERVER_PARAMS = [
        'request_header_max_size' => 1024 * 16,
        'uri' => 'tcp://127.0.0.1:8080',
        'X-Powered-By' => 'ratchet/rfc6455',
        'strict_sub_protocol_check' => true
    ];

    /** @var array */
    protected $serverParams;

    /** @var RouteCollection */
    protected $routes;

    /** @var FilterCollection */
    protected $filters;

    /** @var MatcherFactory */
    protected $matcherFactory;

    /** @var WebsocketNegotiator */
    protected $negotiator;

    /** @var HandlerFactory */
    protected $handlerFactory;

    /** @var TcpServer */
    protected $tcpServer;

    /** @var RequestParserInterface */
    protected $requestParser;


    /**
     *
     * Server parameters:
     *
     * The serverParams is an array provided in the constructor.
     * The following parameters are available:
     *
     * "tcp_server" An instance of a react TcpServer.
     *
     * "uri" The uri to use when creating a new TcpServer.
     * Mutually exclusive with "tcp_server".
     *
     * "tcp_context" Context parameters passed to a new
     * TcpServer.
     * Mutually exclusive with "tcp_server".
     *
     * "request_header_max_size" Maximum header size for HTTP
     * requests.
     *
     * Server parameters are available in every request via
     * getServerParams()
     *
     *
     * @param LoopInterface $loop
     * @param array $serverParams
     */
    public function __construct(LoopInterface $loop, array $serverParams = [])
    {
        $this->serverParams = array_replace([], self::DEFAULT_SERVER_PARAMS, $serverParams);
        $this->matcherFactory = new MatcherFactory($this->serverParams);
        $this->routes = new RouteCollection($this->serverParams, $this->matcherFactory);
        $this->filters = new FilterCollection($this->serverParams);
        $this->handlerFactory = new HandlerFactory($this->serverParams);
        $this->negotiator = new WebsocketNegotiator($this->serverParams);
        $this->requestParser = new RequestParser($this->serverParams);
        $this->tcpServer = $this->createTcpServer($loop);
        $this->addTcpListeners($this->tcpServer);
    }


    /**
     *
     * Convenience method to add a route.
     *
     * Supports the following options:
     *
     *
     * "match" string | RequestMatcherInterface
     *
     * A pattern compatible with fnmatch() or an implementation of
     * RequestMatcherInterface.
     *
     *
     * "protocol" array
     *
     * An array of subprotocols for this route.
     *
     *
     * "controller" string | ControllerInterface
     *
     * The name of a class implementing ControllerInterface without
     * constructor arguments or an instance of a ControllerInterface.
     *
     *
     * "on_open", "on_close", "on_message", "on_error" callable
     *
     * Alternative to the "controller" option, you can provide
     * callbacks and an anonymous controller will be created for
     * you.
     *
     *
     * "filter" RequestFilterInterface | RequestFilterInterface[]
     *
     * Add one or more filters with the same request matcher as
     * the route.
     *
     *
     * @param array $options
     */
    public function route(array $options): void
    {
        $route = $this->routes->create($options);
        $this->routes->add($route);
        $filter = $options['filter'] ?? [];
        foreach (is_array($filter) ? $filter : [$filter] as $item) {
            $this->filter($route->getRequestMatcher(), $item);
        }
    }


    public function addRoute(Route $route): void
    {
        $this->routes->add($route);
    }


    /**
     * Convenience method to add HTTP request filters.
     *
     * @param string|RequestMatcherInterface $match
     * @param RequestFilterInterface|callable $filter
     */
    public function filter($match, $filter): void
    {
        $filter = $this->filters->create($filter);
        $matcher = $this->matcherFactory->create($match);
        $this->filters->add($matcher, $filter);
    }

    public function addFilter(RequestMatcherInterface $matcher, RequestFilterInterface $filter): void
    {
        $this->filters->add($matcher, $filter);
    }


    protected function onTcpConnection(ConnectionInterface $tcpConnection): void
    {
        $this->requestParser->readRequest($tcpConnection)
            ->then(function (ServerRequestInterface $request) use ($tcpConnection) {

                try {
                    $this->onHttpRequest($request, $tcpConnection);
                } catch (\Throwable $error) {
                    $this->onHttpError($request, $tcpConnection, $error);
                }

            }, function (\Throwable $throwable) use ($tcpConnection) {

                $this->onTcpError($tcpConnection, $throwable);

            })
            ->then(null, function (\Throwable $throwable) use ($tcpConnection) {

                // error handling threw an error, give up and pass the error on
                $tcpConnection->end();
                $this->emit('error', [$throwable]);

            });
    }


    protected function onTcpError(ConnectionInterface $tcpConnection, \Throwable $error): void
    {
        $tcpConnection->end();
        $this->emit('error', [$error]);
    }


    protected function onHttpRequest(ServerRequestInterface $request, ConnectionInterface $tcpConnection): void
    {
        $request = $this->filters->apply($request);
        $route = $this->routes->match($request);

        if (!$route) {
            $msg = sprintf('No route found for %s %s', $request->getMethod(), $request->getUri());
            throw ResponseException::create(404, $msg, $msg);
        }

        $response = $this->negotiator->handshake(
            $request,
            $route->getSupportedSubProtocols()
        );

        $tcpConnection->write(str($response));

        $handler = $this->handlerFactory->create($request, $tcpConnection);

        new ControllerDelegation(
            $route->getController(),
            $handler->getWebSocket(),
            $this
        );
    }


    protected function onHttpError(ServerRequestInterface $request, ConnectionInterface $tcpConnection, \Throwable $error): void
    {
        if ($error instanceof ResponseException) {
            $tcpConnection->write(str($error->getResponse()));
        }
        $tcpConnection->end();
        $this->emit('error', [$error]);
    }


    protected function createTcpServer(LoopInterface $loop): TcpServer
    {
        $tcp_server = $this->serverParams['tcp_server'] ?? null;
        $tcp_context = $this->serverParams['tcp_context'] ?? [];
        $uri = $this->serverParams['uri'] ?? null;

        if (is_null($tcp_server) && is_null($uri)) {
            $msg = sprintf('Cannot create tcp server. You have to provide one of the server parameters "uri" or "tcp_server".');
            throw new \InvalidArgumentException($msg);
        }

        if (!is_null($tcp_server) && !is_null($uri) && $uri !== self::DEFAULT_SERVER_PARAMS['uri']) {
            $msg = sprintf('You cannot provide both server parameters "uri" and "tcp_server".');
            throw new \InvalidArgumentException($msg);
        }

        if (!is_null($tcp_server) && array_key_exists('tcp_context', $this->serverParams)) {
            $msg = sprintf('You cannot provide both server parameters "tcp_context" and "tcp_server".');
            throw new \InvalidArgumentException($msg);
        }

        if (!is_null($tcp_server)) {
            if (!$tcp_server instanceof TcpServer) {
                $msg = sprintf('Expected server parameter "tcp_server" to implement %s, got %s.',
                    TcpServer::class,
                    is_object($tcp_server) ? get_class($tcp_server) : gettype($tcp_server));
                throw new \InvalidArgumentException($msg);
            }
            return $tcp_server;
        }

        if (!is_null($uri)) {
            if (!is_string($uri)) {
                $msg = sprintf('Expected server parameter "uri" to be a string, got %s.', gettype($uri));
                throw new \InvalidArgumentException($msg);
            }
            return new TcpServer($uri, $loop, $tcp_context);
        }
    }


    protected function addTcpListeners(ServerInterface $tcpServer): void
    {
        $tcpServer->on('connection', function (ConnectionInterface $tcpConnection) {
            $this->onTcpConnection($tcpConnection);
        });
        $tcpServer->on('error', function (\Throwable $error) {
            $this->emit('error', [$error]);
        });
    }


    public function getAddress()
    {
        return $this->tcpServer->getAddress();
    }

    public function pause()
    {
        $this->tcpServer->pause();
    }

    public function resume()
    {
        $this->tcpServer->resume();
    }

    public function close()
    {
        $this->tcpServer->close();
    }

}
