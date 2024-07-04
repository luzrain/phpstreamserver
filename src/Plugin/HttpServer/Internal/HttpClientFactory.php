<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer\Internal;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Driver\ClientFactory;
use Amp\Http\Server\Driver\ConnectionLimitingClientFactory;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Socket\Socket;
use Luzrain\PHPStreamServer\Internal\ServerStatus\TrafficStatus;
use Psr\Log\LoggerInterface;

final readonly class HttpClientFactory implements ClientFactory
{
    private ClientFactory $clientFactory;

    public function __construct(
        LoggerInterface $logger,
        int|null $connectionLimitPerIp,
        private TrafficStatus $trafficStatisticStore,
        private \Closure|null $onConnectCallback = null,
        private \Closure|null $onCloseCallback = null,
    ) {
        $clientFactory = new SocketClientFactory($logger);

        if ($connectionLimitPerIp !== null) {
            $clientFactory = new ConnectionLimitingClientFactory($clientFactory, $logger, $connectionLimitPerIp);
        }

        $this->clientFactory = $clientFactory;
    }

    public function createClient(Socket $socket): Client|null
    {
        $client = $this->clientFactory->createClient($socket);

        if ($client !== null) {
            $this->onConnect($socket, $client);
            $client->onClose(fn() => $this->onClose($socket, $client));
        }

        return $client;
    }

    private function onConnect(Socket $socket, Client $client): void
    {
        $this->trafficStatisticStore->addConnection($socket);

        if ($this->onConnectCallback !== null) {
            ($this->onConnectCallback)($client);
        }
    }

    private function onClose(Socket $socket, Client $client): void
    {
        $this->trafficStatisticStore->removeConnection($socket);

        if ($this->onCloseCallback !== null) {
            ($this->onCloseCallback)($client);
        }
    }
}
