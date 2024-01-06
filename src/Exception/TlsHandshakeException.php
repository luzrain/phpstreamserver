<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Exception;

final class TlsHandshakeException extends \Exception
{
    public function __construct(string $message = '')
    {
        if ($message === '') {
            $message = 'SSL handshake error';
        } elseif (\str_ends_with($message, 'http request')) {
            $message = 'The plain HTTP request was sent to HTTPS port';
        } else {
            $message = \ltrim(\str_replace('stream_socket_enable_crypto():', '', $message));
        }

        parent::__construct($message);
    }
}
