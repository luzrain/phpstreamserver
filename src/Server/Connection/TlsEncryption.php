<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Connection;

use Luzrain\PHPStreamServer\Exception\TlsHandshakeException;

final readonly class TlsEncryption
{
    /**
     * @param resource $socket
     */
    public function __construct(private mixed $socket)
    {
    }

    /**
     * @throws TlsHandshakeException
     */
    public function encrypt(): void
    {
        \stream_set_blocking($this->socket, true);
        $error = '';
        \set_error_handler(static function (int $type, string $message) use (&$error): bool {
            $error = $message;
            return true;
        });
        $tlsHandshakeCompleted = (bool) \stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
        \restore_error_handler();
        if (!$tlsHandshakeCompleted) {
            throw new TlsHandshakeException($error);
        }
    }
}
