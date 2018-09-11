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
use TS\WebSockets\Http\CallbackRequestFilter;
use TS\WebSockets\Http\FilterCollection;
use TS\WebSockets\Http\RequestFilterInterface;
use TS\WebSockets\Http\RequestParser;
use TS\WebSockets\Http\RequestParserInterface;
use TS\WebSockets\Http\ResponseException;
use TS\WebSockets\Http\WebsocketNegotiator;
use TS\WebSockets\Routing\RequestMatcherInterface;
use TS\WebSockets\Routing\Route;
use TS\WebSockets\Routing\RouteCollection;
use TS\WebSockets\Routing\UrlPatternRequestMatcher;
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
        'uri' => 'tcp://127.0.0.1:8080'
    ];

    /** @var array */
    protected $serverParams;

    /** @var RouteCollection */
    protected $routes;

    /** @var FilterCollection */
    protected $filters;

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
     * "on_error" A callback for the error event.
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
        $this->routes = new RouteCollection();
        $this->filters = new FilterCollection();
        $this->serverParams = array_replace([], self::DEFAULT_SERVER_PARAMS, $serverParams);
        $this->requestParser = $this->createRequestParser();
        $this->handlerFactory = $this->createHandlerFactory();
        $this->negotiator = $this->createNegotiator();
        $this->tcpServer = $this->createTcpServer($loop);
        $this->tcpServer->on('connection', function (ConnectionInterface $tcpConnection) {
            $this->onTcpConnection($tcpConnection);
        });
        $this->tcpServer->on('error', function (\Throwable $error) {
            $this->emit('error', [$error]);
        });
        $on_error = $serverParams['on_error'] ?? null;
        if ($on_error) {
            if (!is_callable($on_error)) {
                throw new \InvalidArgumentException('Invalid value for option "on_error". Expected a callable.');
            }
            $this->on('error', $on_error);
        }
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
        $route = Route::create($options);
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
        if (is_callable($filter)) {
            $filter = new CallbackRequestFilter($filter);
        } else if (!$filter instanceof RequestFilterInterface) {
            throw new \InvalidArgumentException('Invalid argument $filter. Expected callable or RequestFilterInterface.');
        }
        if (is_string($match)) {
            $match = new UrlPatternRequestMatcher($match);
        } else if (!$match instanceof RequestMatcherInterface) {
            throw new \InvalidArgumentException('Invalid argument $match. Expected string or RequestMatcherInterface.');
        }
        $this->filters->add($match, $filter);
    }

    public function addFilter(RequestMatcherInterface $matcher, RequestFilterInterface $filter): void
    {
        $this->filters->add($matcher, $filter);
    }


    protected function onTcpConnection(ConnectionInterface $tcpConnection): void
    {
        $this->requestParser->readRequest($tcpConnection)
            ->then(function (ServerRequestInterface $request) use ($tcpConnection) {

                $this->onHttpRequest($request, $tcpConnection);

            }, function (\Throwable $throwable) use ($tcpConnection) {

                $tcpConnection->end();
                $this->emit('error', [$throwable]);

            })
            ->then(null, function (\Throwable $throwable) use ($tcpConnection) {

                if ($throwable instanceof ResponseException) {
                    $tcpConnection->write(str($throwable->getResponse()));
                }

                $tcpConnection->end();
                $this->emit('error', [$throwable]);

            });
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


    protected function createHandlerFactory(): HandlerFactory
    {
        return new HandlerFactory();
    }

    protected function createNegotiator(): WebsocketNegotiator
    {
        return new WebsocketNegotiator();
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new RequestParser($this->serverParams);
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
