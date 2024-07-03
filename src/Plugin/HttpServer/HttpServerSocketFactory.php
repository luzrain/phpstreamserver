<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer;

use Amp\Cancellation;
use Amp\Http\Server\Driver\ConnectionLimitingServerSocketFactory;
use Amp\Socket\BindContext;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\Socket\ServerSocket;
use Amp\Socket\ServerSocketFactory;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Sync\LocalSemaphore;
use Amp\Sync\PosixSemaphore;
use Amp\Sync\Semaphore;
use Luzrain\PHPStreamServer\Internal\ServerStatus\TrafficStatus;

final readonly class HttpServerSocketFactory implements ServerSocketFactory
{
    private ServerSocketFactory $serverSocketFactory;

    /**
     * @param int<1, max>|null $connectionLimit
     */
    public function __construct(
        int|null $connectionLimit,
        private TrafficStatus $trafficStatisticStore,
    ) {
        $serverSocketFactory = new ResourceServerSocketFactory();

        if ($connectionLimit !== null) {
            $semaphoreFactory =
                /** @param int<1, max> $maxLocks */
                static fn(int $maxLocks): Semaphore => \extension_loaded('sysvmsg')
                ? PosixSemaphore::create($maxLocks)
                : new LocalSemaphore($maxLocks)
            ;

            $serverSocketFactory = new ConnectionLimitingServerSocketFactory($semaphoreFactory($connectionLimit), $serverSocketFactory);
        }

        $this->serverSocketFactory = $serverSocketFactory;
    }

    public function listen(SocketAddress|string $address, ?BindContext $bindContext = null): ServerSocket
    {
        $serverSocket = $this->serverSocketFactory->listen($address, $bindContext);

        return new class ($serverSocket, $this->trafficStatisticStore) implements ServerSocket {
            public function __construct(private readonly ServerSocket $serverSocket, private readonly TrafficStatus $trafficStatisticStore)
            {
            }

            public function close(): void
            {
                $this->serverSocket->close();
            }

            public function isClosed(): bool
            {
                return $this->serverSocket->isClosed();
            }

            public function onClose(\Closure $onClose): void
            {
                $this->serverSocket->onClose($onClose);
            }

            public function accept(?Cancellation $cancellation = null): ?Socket
            {
                if (null === $socket = $this->serverSocket->accept($cancellation)) {
                    return null;
                }

                return new TrafficCountingSocket($socket, $this->trafficStatisticStore);
            }

            public function getAddress(): SocketAddress
            {
                return $this->serverSocket->getAddress();
            }

            public function getBindContext(): BindContext
            {
                return $this->serverSocket->getBindContext();
            }
        };
    }
}
