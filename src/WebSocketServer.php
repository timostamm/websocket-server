<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 07.09.18
 * Time: 17:17
 */

namespace TS\WebSockets;


use Evenement\EventEmitter;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use React\Socket\TcpServer;
use RuntimeException;
use Throwable;
use TS\WebSockets\Http\FilterCollection;
use TS\WebSockets\Http\MatcherFactory;
use TS\WebSockets\Http\RequestFilterInterface;
use TS\WebSockets\Http\RequestMatcherInterface;
use TS\WebSockets\Http\RequestParser;
use TS\WebSockets\Http\ResponseException;
use TS\WebSockets\Http\RouteStarsFilters;
use TS\WebSockets\Protocol\TcpConnections;
use TS\WebSockets\Protocol\WebSocketHandlerFactory;
use TS\WebSockets\Protocol\WebSocketNegotiator;
use TS\WebSockets\Routing\ControllerDelegationFactory;
use TS\WebSockets\Routing\Route;
use TS\WebSockets\Routing\RouteCollection;
use function GuzzleHttp\Psr7\str;
use function React\Promise\all;
use function React\Promise\resolve;


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
        'strict_sub_protocol_check' => true,
        'sub_protocols' => [],
        'shutdown_signals' => []
    ];

    /** @var LoopInterface */
    protected $loop;

    /** @var array */
    protected $serverParams;

    /** @var RequestParser */
    protected $requestParser;

    /** @var MatcherFactory */
    protected $matcherFactory;

    /** @var FilterCollection */
    protected $filters;

    /** @var RouteCollection */
    protected $routes;

    /** @var WebSocketNegotiator */
    protected $negotiator;

    /** @var WebSocketHandlerFactory */
    protected $webSocketHandlers;

    /** @var ControllerDelegationFactory */
    protected $controllerDelegations;

    /** @var TcpServer */
    protected $tcpServer;

    /** @var TcpConnections */
    protected $tcpConnections;

    /** @var Deferred */
    private $shuttingDown;


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
        $this->loop = $loop;
        $this->serverParams = array_replace([], self::DEFAULT_SERVER_PARAMS, $serverParams, [
            'loop' => $loop
        ]);
        $onError = function (Throwable $error) {
            $this->emit('error', [$error]);
        };
        $this->requestParser = new RequestParser($this->serverParams);
        $this->matcherFactory = new MatcherFactory($this->serverParams);
        $this->filters = new FilterCollection($this->serverParams);
        $this->routes = new RouteCollection($this->serverParams, $this->matcherFactory);
        $this->negotiator = new WebSocketNegotiator($this->serverParams);
        $this->webSocketHandlers = new WebSocketHandlerFactory($this->serverParams);
        $this->controllerDelegations = new ControllerDelegationFactory($this->serverParams, $onError);
        $this->tcpServer = $this->createTcpServer($loop);
        $this->tcpServer->on('error', $onError);
        $this->tcpConnections = new TcpConnections($this->tcpServer, function (ConnectionInterface $connection) {
            $this->onTcpConnection($connection);
        });
        $this->addShutdownSignals($this->serverParams);
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

        if (is_string($options['match'] ?? false)) {
            $this->addFilter($route->getRequestMatcher(), new RouteStarsFilters($options['match']));
        }

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


    public function shutDown(float $timeout = 10.0, bool $stopLoop = true): PromiseInterface
    {
        $this->shuttingDown = new Deferred();

        $this->loop->addTimer($timeout, function () {
            $this->emit('error', [new RuntimeException('Shutdown timeout - shutting down NOW')]);
            $this->shuttingDown->resolve();
        });

        all([
            // stop accepting new connections and wait for current to close
            $this->tcpConnections->shutDown(),

            // shutdown controllers
            resolve($this->controllerDelegations->shutDown())->always(function () {

                // then gracefully close web socket connections
                return $this->webSocketHandlers->shutDown();
            }),

        ])->always(function () {
            $this->shuttingDown->resolve();
        });

        return $this->shuttingDown
            ->promise()
            ->always(function () use ($stopLoop) {
                $this->tcpServer->close();
                if ($stopLoop) {
                    $this->loop->stop();
                }
            });
    }


    public function stats(): array
    {
        return $this->tcpConnections->stats()
            + $this->webSocketHandlers->stats()
            + $this->controllerDelegations->stats();
    }


    protected function onTcpConnection(ConnectionInterface $tcpConnection): void
    {
        $this->requestParser->readRequest($tcpConnection)
            ->then(function (ServerRequestInterface $request) use ($tcpConnection) {

                try {

                    $this->onHttpRequest($request, $tcpConnection);

                } catch (ResponseException $error) {

                    // TODO this is part of normal control flow, but we should log this somehow

                    $tcpConnection->write(str($error->getResponse()));
                    $tcpConnection->end();

                }

            }, function (Throwable $error) use ($tcpConnection) {

                // error handling HTTP request
                $tcpConnection->end();
                $this->emit('error', [$error]);

            })
            ->then(null, function (Throwable $throwable) use ($tcpConnection) {

                // error handling threw an error, give up and pass the error on
                $tcpConnection->end();
                $this->emit('error', [$throwable]);

            });
    }


    protected function onHttpRequest(ServerRequestInterface $request, ConnectionInterface $tcpConnection): void
    {
        if ($this->shuttingDown) {
            throw ResponseException::create(503, null, 'shutdown');
        }

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

        $handler = $this->webSocketHandlers->add($request, $tcpConnection);

        $this->controllerDelegations->add($route->getController(), $handler->getWebSocket());
    }


    protected function createTcpServer(LoopInterface $loop): TcpServer
    {
        $tcp_server = $this->serverParams['tcp_server'] ?? null;
        $tcp_context = $this->serverParams['tcp_context'] ?? [];
        $uri = $this->serverParams['uri'] ?? null;

        if (is_null($tcp_server) && is_null($uri)) {
            $msg = sprintf('Cannot create tcp server. You have to provide one of the server parameters "uri" or "tcp_server".');
            throw new InvalidArgumentException($msg);
        }

        if (!is_null($tcp_server) && !is_null($uri) && $uri !== self::DEFAULT_SERVER_PARAMS['uri']) {
            $msg = sprintf('You cannot provide both server parameters "uri" and "tcp_server".');
            throw new InvalidArgumentException($msg);
        }

        if (!is_null($tcp_server) && array_key_exists('tcp_context', $this->serverParams)) {
            $msg = sprintf('You cannot provide both server parameters "tcp_context" and "tcp_server".');
            throw new InvalidArgumentException($msg);
        }

        if (!is_null($tcp_server)) {
            if (!$tcp_server instanceof TcpServer) {
                $msg = sprintf('Expected server parameter "tcp_server" to implement %s, got %s.',
                    TcpServer::class,
                    is_object($tcp_server) ? get_class($tcp_server) : gettype($tcp_server));
                throw new InvalidArgumentException($msg);
            }
            return $tcp_server;
        }

        if (!is_null($uri)) {
            if (!is_string($uri)) {
                $msg = sprintf('Expected server parameter "uri" to be a string, got %s.', gettype($uri));
                throw new InvalidArgumentException($msg);
            }
            return new TcpServer($uri, $loop, $tcp_context);
        }
    }


    protected function addShutdownSignals(array $serverParams): void
    {
        $signals = $serverParams['shutdown_signals'] ?? [];
        if (empty($signals)) {
            return;
        }
        foreach ($signals as $signal) {
            $this->loop->addSignal($signal, $func = function ($signal) use (&$func) {
                $this->loop->removeSignal($signal, $func);
                $this->shutDown();
            });
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
