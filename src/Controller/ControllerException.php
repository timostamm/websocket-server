<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 12:39
 */

namespace TS\WebSockets\Controller;


use RuntimeException;
use Throwable;
use TS\WebSockets\WebSocket;


class ControllerException extends RuntimeException
{


    public static function methodCall($controller, string $method, array $args, Throwable $previous): self
    {
        $args = array_map([self::class, 'formatVar'], $args);
        $msg = sprintf('Caught %s when invoking %s::%s(%s): %s', get_class($previous), get_class($controller), $method, join(', ', $args), $previous->getMessage());
        return new self($msg, 0, $previous);
    }


    public static function controller($controller, Throwable $previous): self
    {
        $msg = sprintf('Caught %s from %s: %s', get_class($previous), get_class($controller), $previous->getMessage());
        return new self($msg, 0, $previous);
    }


    protected static function formatVar($var): string
    {
        if (is_string($var)) {
            return '"' . $var . '"';
        }
        if (is_bool($var)) {
            return $var ? 'true' : 'false';
        }
        if (is_null($var)) {
            return 'null';
        }
        if (is_scalar($var)) {
            return strval($var);
        }
        if (is_array($var)) {
            return 'array(' . count($var) . ')';
        }
        if ($var instanceof WebSocket) {
            return 'object WebSocket(' . $var . ')';
        }
        if (is_object($var)) {
            if (method_exists($var, '__toString')) {
                return strval($var);
            }
            return 'object ' . get_class($var);
        }
        return gettype($var);
    }


    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }


}
