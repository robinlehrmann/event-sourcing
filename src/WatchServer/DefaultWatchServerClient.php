<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

use function base64_encode;
use function fclose;
use function restore_error_handler;
use function serialize;
use function set_error_handler;
use function stream_socket_client;
use function stream_socket_sendto;
use function stream_socket_shutdown;
use function strpos;

use const STREAM_CLIENT_ASYNC_CONNECT;
use const STREAM_CLIENT_CONNECT;
use const STREAM_SHUT_RDWR;

final class DefaultWatchServerClient implements WatchServerClient
{
    private string $host;

    /** @var resource|null */
    private $socket;

    /**
     * @param string $host The server host
     */
    public function __construct(string $host)
    {
        if (strpos($host, '://') === false) {
            $host = 'tcp://' . $host;
        }

        $this->host = $host;
        $this->socket = null;
    }

    public function send(AggregateChanged $event): void
    {
        $socket = $this->createSocket();

        if (!$socket) {
            throw new SendingFailed('socket connection could not be established');
        }

        $encodedPayload = base64_encode(serialize($event->serialize())) . "\n";

        set_error_handler([self::class, 'nullErrorHandler']);

        try {
            if (stream_socket_sendto($socket, $encodedPayload) !== -1) {
                return;
            }

            $this->closeSocket();
            $socket = $this->createSocket();

            if (!$socket) {
                throw new SendingFailed('socket connection could not be established');
            }

            if (stream_socket_sendto($socket, $encodedPayload) !== -1) {
                return;
            }
        } finally {
            restore_error_handler();
        }

        throw new SendingFailed('unknown error');
    }

    /**
     * @return resource|null
     */
    private function createSocket()
    {
        if ($this->socket) {
            return $this->socket;
        }

        set_error_handler([self::class, 'nullErrorHandler']);

        try {
            $socket = stream_socket_client(
                $this->host,
                $errno,
                $errstr,
                3,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
            );

            if (!$socket) {
                return null;
            }

            $this->socket = $socket;

            return $socket;
        } finally {
            restore_error_handler();
        }
    }

    private function closeSocket(): void
    {
        $socket = $this->socket;

        if (!$socket) {
            return;
        }

        if (!stream_socket_shutdown($socket, STREAM_SHUT_RDWR)) {
            throw new SendingFailed('socket shutdown failed');
        }

        fclose($socket);

        $this->socket = null;
    }

    /** @internal */
    public function nullErrorHandler(int $errno, string $errstr): bool
    {
        return false;
    }
}
