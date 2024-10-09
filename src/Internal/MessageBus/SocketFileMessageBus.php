<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use Amp\Future;
use Amp\Socket\ConnectException;
use Amp\Socket\DnsSocketConnector;
use Amp\Socket\SocketConnector;
use Amp\Socket\StaticSocketConnector;
use function Amp\async;
use function Amp\delay;

/**
 * @internal
 */
final class SocketFileMessageBus implements MessageBus
{
    private SocketConnector $connector;

    public function __construct(string $socketFile)
    {
        $this->connector = new StaticSocketConnector("unix://{$socketFile}", new DnsSocketConnector());
    }

    public function dispatch(Message $message): Future
    {
        $connector = &$this->connector;

        return async(static function () use (&$connector, &$message): mixed {
            while (true) {
                try {
                    $socket = $connector->connect('');
                    break;
                } catch (ConnectException) {
                    delay(0.01);
                }
            }

            $serializedData = \serialize($message);
            $sizeMark = \str_pad((string) \strlen($serializedData), 10, '0', STR_PAD_LEFT);

            $socket->write($sizeMark . $serializedData);
            $buffer = $socket->read(limit: PHP_INT_MAX);

            return \unserialize($buffer);
        });
    }
}
