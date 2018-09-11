<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 08:10
 */

namespace TS\WebSockets\Routing;


use Psr\Http\Message\ServerRequestInterface;
use TS\WebSockets\ControllerInterface;
use TS\WebSockets\WebSocket;


class Route
{

    private $requestMatcher;
    private $subProtocols;
    private $controller;


    public static function create(array $options): Route
    {
        $match = $options['match'] ?? '*';
        $matcher = is_string($match) ? new UrlPatternRequestMatcher($match) : $match;
        if (!$matcher instanceof RequestMatcherInterface) {
            $msg = sprintf('Option "match" must be a pattern string or an implementation of %s.', RequestMatcherInterface::class);
            throw new \InvalidArgumentException($msg);
        }

        $protocols = $options['protocols'] ?? [];
        if (!is_array($protocols)) {
            $msg = sprintf('Option "protocols" must be a pattern (compatible with fnmatch()) or an implementation of %s.', RequestMatcherInterface::class);
            throw new \InvalidArgumentException($msg);
        }

        $controller = $options['controller'] ?? null;
        if (is_string($controller)) {
            try {
                $ref = new \ReflectionClass($controller);
                $controller = $ref->newInstance();
            } catch (\Exception $exception) {
                $msg = sprintf('Unable to instantiate controller %s: %s', $controller, $exception->getMessage());
                throw new \InvalidArgumentException($msg, 0, $exception);
            }
        } else if (is_object($controller)) {
            if (!$controller instanceof ControllerInterface) {
                $msg = sprintf('Instance of %s provided for option "controller" does not implement %s.', get_class($controller), ControllerInterface::class);
                throw new \InvalidArgumentException($msg);
            }
        } else if (!is_null($controller)) {
            $msg = sprintf('Invalid value for option "controller". Expected string or %s, got %s.', ControllerInterface::class, gettype($controller));
            throw new \InvalidArgumentException($msg);
        }

        $on_open = $options['on_open'] ?? null;
        $on_close = $options['on_close'] ?? null;
        $on_message = $options['on_message'] ?? null;
        $on_error = $options['on_error'] ?? null;
        if (!is_null($on_open) && !is_callable($on_open)) {
            $msg = sprintf('Option "on_open" must be callable, got %s.', gettype($on_open));
            throw new \InvalidArgumentException($msg);
        }
        if (!is_null($on_close) && !is_callable($on_close)) {
            $msg = sprintf('Option "on_close" must be callable, got %s.', gettype($on_close));
            throw new \InvalidArgumentException($msg);
        }
        if (!is_null($on_message) && !is_callable($on_message)) {
            $msg = sprintf('Option "on_message" must be callable, got %s.', gettype($on_message));
            throw new \InvalidArgumentException($msg);
        }
        if (!is_null($on_error) && !is_callable($on_error)) {
            $msg = sprintf('Option "on_error" must be callable, got %s.', gettype($on_error));
            throw new \InvalidArgumentException($msg);
        }
        if (!is_null($on_open) || !is_null($on_close) || !is_null($on_message) || !is_null($on_error)) {
            if ($controller) {
                $msg = 'You cannot provide option "controller" and one of the "on_*" options.';
                throw new \InvalidArgumentException($msg);
            }
            $controller = new class($on_open, $on_close, $on_message, $on_error) implements ControllerInterface
            {

                private $on_open;
                private $on_close;
                private $on_message;
                private $on_error;

                public function __construct(?callable $on_open, ?callable $on_close, ?callable $on_message, ?callable $on_error)
                {
                    $this->on_open = $on_open;
                    $this->on_close = $on_close;
                    $this->on_message = $on_message;
                    $this->on_error = $on_error;
                }

                function onOpen(WebSocket $socket): void
                {
                    $fn = $this->on_open;
                    if ($fn) {
                        $fn($socket);
                    }
                }

                function onMessage(WebSocket $from, string $payload, bool $binary): void
                {
                    $fn = $this->on_message;
                    if ($fn) {
                        $fn($from, $payload, $binary);
                    }
                }

                function onClose(WebSocket $socket): void
                {
                    $fn = $this->on_close;
                    if ($fn) {
                        $fn($socket);
                    }
                }

                function onError(WebSocket $socket, \Throwable $error): void
                {
                    $fn = $this->on_error;
                    if ($fn) {
                        $fn($socket, $error);
                    }
                }

            };
        }

        if (!$controller) {
            $msg = sprintf('Missing controller. You have to provide either an implementation or a class name of %s as the option "controller", or at least one of the options "on_open", "on_close", "on_error", "on_message".', ControllerInterface::class);
            throw new \InvalidArgumentException($msg);
        }

        return new Route($matcher, $controller, $protocols);
    }


    public function __construct(RequestMatcherInterface $requestMatcher, ControllerInterface $controller, array $subProtocols)
    {
        $this->requestMatcher = $requestMatcher;
        $this->controller = $controller;
        $this->subProtocols = $subProtocols;
    }


    public function matches(ServerRequestInterface $request): bool
    {
        return $this->requestMatcher->matches($request);
    }


    public function getRequestMatcher(): RequestMatcherInterface
    {
        return $this->requestMatcher;
    }


    public function getController(): ControllerInterface
    {
        return $this->controller;
    }


    public function getSupportedSubProtocols(): array
    {
        return $this->subProtocols;
    }


}
