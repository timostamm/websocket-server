<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 07.09.18
 * Time: 17:17
 */

namespace TS\Websockets;


use Evenement\EventEmitter;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use React\Socket\TcpServer;
use TS\Websockets\Connections\ConnectionFactory;
use TS\Websockets\Connections\ConnectionFactoryInterface;
use TS\Websockets\Connections\ControllerDelegation;
use TS\Websockets\Http\FilterCollection;
use TS\Websockets\Http\RequestFilterInterface;
use TS\Websockets\Http\RequestParser;
use TS\Websockets\Http\RequestParserInterface;
use TS\Websockets\Http\ResponseException;
use TS\Websockets\Http\WebsocketNegotiator;
use TS\Websockets\Routing\RequestMatcherInterface;
use TS\Websockets\Routing\Route;
use TS\Websockets\Routing\RouteCollection;
use TS\Websockets\Routing\UrlPatternRequestMatcher;
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
class WebsocketServer extends EventEmitter implements ServerInterface
{

    const EVENT_CONNECTION = 'connection';
    const EVENT_ERROR = 'error';

    const DEFAULT_SERVER_PARAMS = [
        'request_header_max_size' => 1024 * 16,
        'uri' => 'tcp://localhost:8080'
    ];

    /** @var array */
    protected $serverParams;

    /** @var RouteCollection */
    protected $routes;

    /** @var FilterCollection */
    protected $filters;

    /** @var WebsocketNegotiator */
    protected $negotiator;

    /** @var ConnectionFactoryInterface */
    protected $connectionFactory;

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
        $this->routes = new RouteCollection();
        $this->filters = new FilterCollection();

        $this->serverParams = array_replace($serverParams, self::DEFAULT_SERVER_PARAMS);
        $this->requestParser = $this->createRequestParser();
        $this->connectionFactory = $this->createConnectionFactory();
        $this->negotiator = $this->createNegotiator();
        $this->tcpServer = $this->createTcpServer($loop);
        $this->tcpServer->on('connection', function (ConnectionInterface $tcpConnection) {
            $this->onTcpConnection($tcpConnection);
        });
        $this->tcpServer->on('error', function (\Throwable $error) {
            $this->emit('error', [$error]);
        });
    }


    /**
     * @param string $urlPattern
     * @param ControllerInterface|string $controller
     * @param array $options
     */
    public function addRoute(string $urlPattern, $controller, array $options = []): void
    {
        if ($controller instanceof ControllerInterface) {
            $ctrl = $controller;
        } else if (is_string($controller)) {
            try {
                $ref = new \ReflectionClass($controller);
                $ctrl = $ref->newInstance();
            } catch (\Exception $exception) {
                $msg = sprintf('Unable to instantiate controller %s: %s', $controller, $exception->getMessage());
                throw new \InvalidArgumentException($msg, 0, $exception);
            }
            if ($ctrl instanceof ControllerInterface) {
                $msg = sprintf('Invalid argument for controller. Value must be a controller instance or a class name.');
                throw new \InvalidArgumentException($msg);
            }
        } else {
            $msg = sprintf('Invalid argument for controller. Value must be a controller instance or a class name.');
            throw new \InvalidArgumentException($msg);
        }


        $matcher = new UrlPatternRequestMatcher($urlPattern);

        $protocols = $options['protocols'] ?? [];

        $route = new Route($matcher, $ctrl, $protocols);

        $this->routes->add($route);
    }


    public function addRouteInstance(Route $route): void
    {
        $this->routes->add($route);
    }


    public function addFilter(string $urlPattern, RequestFilterInterface $filter): void
    {
        $this->filters->add(new UrlPatternRequestMatcher($urlPattern), $filter);
    }

    public function addFilterInstance(RequestMatcherInterface $matcher, RequestFilterInterface $filter): void
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

        $websocket = $this->connectionFactory->createConnection($request, $tcpConnection);

        new ControllerDelegation(
            $route->getController(),
            $websocket, $this
        );
    }


    protected function createConnectionFactory(): ConnectionFactoryInterface
    {
        return new ConnectionFactory();
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
