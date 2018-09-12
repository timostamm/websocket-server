<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 09:59
 */

namespace TS\WebSockets\Protocol;


use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use function React\Promise\resolve;


class TcpConnections
{

    /** @var ServerInterface */
    private $server;

    /** @var \SplObjectStorage */
    private $connections;

    /** @var Deferred */
    private $shuttingDown = null;

    /** @var callable */
    private $onConnection;

    /** @var callable */
    private $onError;


    public function __construct(ServerInterface $server, callable $onConnection, callable $onError)
    {
        $this->server = $server;
        $this->onConnection = $onConnection;
        $this->onError = $onError;
        $this->connections = new \SplObjectStorage();
        $server->on('error', $onError);
        $server->on('connection', $onConnection);
        $server->on('connection', [$this, 'onOpen']);
    }


    public function onOpen(ConnectionInterface $tcpConnection): void
    {
        $this->connections->attach($tcpConnection);
        $tcpConnection->once('close', function () use ($tcpConnection) {
            $this->onClose($tcpConnection);
        });
        if ($this->shuttingDown) {
            $tcpConnection->close();
        }
    }


    protected function onClose(ConnectionInterface $tcpConnection): void
    {
        $this->connections->detach($tcpConnection);
        if ($this->shuttingDown && $this->connections->count() === 0) {
            $this->shuttingDown->resolve();
        }
    }


    public function shutDown(): PromiseInterface
    {
        $this->server->removeListener('connection', $this->onConnection);
        if ($this->connections->count() === 0) {
            return resolve();
        }
        $this->shuttingDown = new Deferred();
        return $this->shuttingDown->promise();
    }

}
