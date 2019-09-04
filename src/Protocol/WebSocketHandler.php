<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 10:52
 */

namespace TS\WebSockets\Protocol;


use BadMethodCallException;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\Socket\ConnectionInterface;
use RuntimeException;
use Throwable;
use TS\WebSockets\WebSocket;


/**
 *
 * Treats a TCP connection as a Websocket connection.
 *
 */
class WebSocketHandler
{


    /** @var MessageBuffer */
    protected $buffer;

    /** @var ConnectionInterface */
    protected $tcpConnection;

    /** @var ServerRequestInterface */
    protected $request;

    /** @var WebSocket */
    protected $webSocket;

    protected $closed = false;
    protected $closing = false;


    public function __construct(
        ServerRequestInterface $request,
        ConnectionInterface $tcpConnection,
        CloseFrameChecker $closeFrameChecker,
        callable $exceptionFactory = null
    )
    {
        $this->webSocket = new WebSocket($this);
        $this->request = $request;
        $this->tcpConnection = $tcpConnection;
        $tcpConnection->on('data', [$this, 'onTcpData']);
        $tcpConnection->on('error', [$this, 'onTcpError']);
        $tcpConnection->once('end', [$this, 'onTcpEnd']);
        $tcpConnection->once('close', [$this, 'onTcpClose']);

        $this->buffer = new MessageBuffer(
            $closeFrameChecker,
            [$this, 'onMessage'],
            [$this, 'onControlFrame'],
            $exceptionFactory
        );
    }


    public function getWebSocket(): WebSocket
    {
        return $this->webSocket;
    }


    public function getTcpConnection(): ConnectionInterface
    {
        return $this->tcpConnection;
    }


    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }


    public function send(string $payload, bool $binary): void
    {
        if ($this->closed) {
            throw new BadMethodCallException('Cannot send data, socket is closed.');
        }
        if ($this->closing) {
            throw new BadMethodCallException('Cannot send data, socket is closing.');
        }
        $op = $binary ? Frame::OP_BINARY : Frame::OP_TEXT;
        $frame = $this->buffer->newFrame($payload, true, $op);
        $this->tcpConnection->write($frame->getContents());
    }


    /**
     *
     * We initiate the closing handshake by sending a
     * close frame.
     *
     * Now we should wait until the client acknowledges
     * the by sending the same close code back.
     *
     * Then we must close the TCP connection.
     *
     *
     * @param int $code
     * @param string $reason
     */
    public function startClose(int $code, string $reason = ''): void
    {
        if ($this->closed) {
            throw new BadMethodCallException('Already closed.');
        }
        if ($this->closing) {
            throw new BadMethodCallException('Already closing.');
        }

        $this->closing = true;

        $frame = $this->buffer->newCloseFrame($code, $reason);
        $this->tcpConnection->write($frame->getContents());

        $this->webSocket->emit('close');
    }


    /**
     *
     * We have to acknowledge the close by sending the same close
     * code back to the client.
     * The client will then close the TCP connection.
     *
     * @param FrameInterface $frame
     */
    protected function ackClose(FrameInterface $frame): void
    {
        $this->closing = true;
        $this->tcpConnection->end($frame->getContents());
        $this->webSocket->emit('close');
    }


    public function isClosed(): bool
    {
        return $this->closed;
    }


    public function isClosing(): bool
    {
        return $this->closing;
    }


    protected function setClosed(): void
    {
        $this->closing = false;
        $this->closed = true;
        $this->tcpConnection->removeListener('error', [$this, 'onTcpError']);
        $this->tcpConnection->removeListener('data', [$this, 'onTcpData']);
        $this->tcpConnection->removeListener('close', [$this, 'onTcpClose']);
        $this->tcpConnection->removeListener('end', [$this, 'onTcpEnd']);
    }


    public function onTcpData($data): void
    {
        $this->buffer->onData($data);
    }


    public function onTcpError(Throwable $throwable): void
    {
        if (!$this->closed && !$this->closing) {
            $this->webSocket->emit('error', [$throwable]);
            $this->webSocket->emit('close');
        }
        // should we close tcp or not?
        //$this->tcpConnection->close();
        $this->setClosed();
    }


    public function onTcpEnd(): void
    {
        if (!$this->closed && !$this->closing) {
            $this->webSocket->emit('error', [new RuntimeException('TCP connection ended without close.')]);
            $this->webSocket->emit('close');
        }
        $this->setClosed();
    }


    public function onTcpClose(): void
    {
        if (!$this->closed && !$this->closing) {
            $this->webSocket->emit('error', [new RuntimeException('TCP connection closed without close.')]);
            $this->webSocket->emit('close');
        }
        $this->setClosed();
    }


    public function onMessage(MessageInterface $message): void
    {
        if ($this->closing) {
            // There should not be any messages at this point
            return;
        }
        $payload = $message->getPayload();
        $binary = $message->isBinary();
        $this->webSocket->emit('message', [$payload, $binary]);
    }


    public function onControlFrame(FrameInterface $frame): void
    {
        switch ($frame->getOpCode()) {

            case Frame::OP_CLOSE:

                // The ratchet MessageBuffer may have created this
                // close frame in response to invalid data received
                // from the client.
                //
                // So the close frame may be either
                // 1) start of a closing handshake initiated by the client
                // 2) the acknowledgment of a closing handshake initiated by us
                // 3) invalid data received from the client

                list($code, $reason) = $this->decodeCloseFrame($frame->getPayload());

                if (strpos($reason, 'Ratchet detected') === 0) {

                    // case 3)
                    $this->startClose($code, $reason);

                    if ($this->closing) {
                        $this->tcpConnection->end();
                    }

                } else if ($this->closing) {

                    // case 2)
                    $this->tcpConnection->end();

                } else {

                    // case 1)
                    // We have to acknowledge the close by sending the same close
                    // code back to the client.
                    // According to the autobahn test suite, it is our responsibility
                    // to close the TCP connection.
                    $this->ackClose($frame);

                }

                break;

            case Frame::OP_PING:

                // When we get a ping, send back a pong with the exact same Payload Data as the ping

                $frame = $this->buffer->newFrame($frame->getPayload(), true, Frame::OP_PONG);
                $this->tcpConnection->write($frame->getContents());

                break;

            case Frame::OP_PONG:

                // We do not initiate pings and ignore all pongs

                break;
        }
    }


    protected function decodeCloseFrame(string $payload): array
    {
        list($code) = array_merge(unpack('n*', substr($payload, 0, 2)));
        $reason = substr($payload, 2);
        return [$code, $reason];
    }


}
