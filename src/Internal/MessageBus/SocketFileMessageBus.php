<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use Amp\Future;
use Amp\Socket\DnsSocketConnector;
use Amp\Socket\RetrySocketConnector;
use Amp\Socket\SocketConnector;
use Amp\Socket\StaticSocketConnector;
use function Amp\async;

final class SocketFileMessageBus implements MessageBus
{
    private SocketConnector $connector;

    public function __construct(string $socketFile)
    {
        $this->connector = new RetrySocketConnector(
            delegate: new StaticSocketConnector("unix://{$socketFile}", new DnsSocketConnector()),
            maxAttempts: 3,
            exponentialBackoffBase: 1,
        );
    }

    public function dispatch(Message $message): Future
    {
        $connector = &$this->connector;

        return async(static function () use (&$connector, &$message): mixed {
            $socket = $connector->connect('');
            $socket->write(\serialize($message));
            $buffer = $socket->read(limit: PHP_INT_MAX);

            return \unserialize($buffer);
        });
    }
}
