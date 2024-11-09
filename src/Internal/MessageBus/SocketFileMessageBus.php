<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use Amp\Future;
use Amp\Socket\ConnectException;
use Amp\Socket\DnsSocketConnector;
use Amp\Socket\SocketConnector;
use Amp\Socket\StaticSocketConnector;
use Amp\Socket\UnixAddress;
use Luzrain\PHPStreamServer\MessageBus\MessageInterface;
use Luzrain\PHPStreamServer\MessageBus\MessageBusInterface;
use function Amp\async;
use function Amp\delay;

/**
 * @internal
 */
final class SocketFileMessageBus implements MessageBusInterface
{
    private bool $stopped = false;
    private \WeakMap $inProgress;
    private SocketConnector $connector;

    public function __construct(string $socketFile)
    {
        $this->inProgress = new \WeakMap();
        $this->connector = new StaticSocketConnector(new UnixAddress($socketFile), new DnsSocketConnector());
    }

    public function dispatch(MessageInterface $message): Future
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

            $serializedWriteData = \serialize($message);
            $socket->write(\pack('Va*', \strlen($serializedWriteData), $serializedWriteData));

            $data = $socket->read(limit: SocketFileMessageHandler::CHUNK_SIZE);
            ['size' => $size, 'data' => $data] = \unpack('Vsize/a*data', $data);

            while (\strlen($data) < $size) {
                $data .= $socket->read(limit: SocketFileMessageHandler::CHUNK_SIZE);
            }

            return \unserialize($data);
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
