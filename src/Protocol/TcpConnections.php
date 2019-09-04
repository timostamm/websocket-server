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
use SplObjectStorage;
use function React\Promise\resolve;


class TcpConnections
{

    /** @var ServerInterface */
    private $server;

    /** @var SplObjectStorage */
    private $connections;

    /** @var Deferred */
    private $shuttingDown = null;

    /** @var callable */
    private $onConnection;


    public function __construct(ServerInterface $server, callable $onConnection)
    {
        $this->server = $server;
        $this->onConnection = $onConnection;
        $this->connections = new SplObjectStorage();
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
        } else {
            $fn = $this->onConnection;
            $fn($tcpConnection);
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


    public function stats(): array
    {
        return [
            'tcp connections' => $this->connections->count()
        ];
    }


}
