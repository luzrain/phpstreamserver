<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use Amp\Future;
use Amp\Socket\ConnectException;
use Amp\Socket\DnsSocketConnector;
use Amp\Socket\SocketConnector;
use Amp\Socket\StaticSocketConnector;
use Amp\Socket\UnixAddress;
use function Amp\async;
use function Amp\delay;

/**
 * @internal
 */
final class SocketFileMessageBus implements MessageBus
{
    private bool $stopped = false;
    private \WeakMap $inProgress;
    private SocketConnector $connector;

    public function __construct(string $socketFile)
    {
        $this->inProgress = new \WeakMap();
        $this->connector = new StaticSocketConnector(new UnixAddress($socketFile), new DnsSocketConnector());
    }

    public function dispatch(Message $message): Future
    {
        if ($this->stopped) {
            return async(static function () {
                while(true) {
                    delay(60);
                }
            });
        }

        $connector = &$this->connector;
        $future = async(static function () use (&$connector, &$message): mixed {
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

        $inProgresss = &$this->inProgress;
        $inProgresss->offsetSet($future, true);
        $future->finally(static fn () => $inProgresss->offsetUnset($future));

        return $future;
    }

    public function stop(): Future
    {
        $this->stopped = true;

        return async(function () {
            while ($this->inProgress->count() > 0) {
                delay(0.01);
            }

            return null;
        });
    }
}
