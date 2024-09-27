<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer\Internal;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Driver\ClientFactory;
use Amp\Http\Server\Driver\ConnectionLimitingClientFactory;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Socket\Socket;
use Psr\Log\LoggerInterface;

final readonly class HttpClientFactory implements ClientFactory
{
    private ClientFactory $clientFactory;

    public function __construct(
        LoggerInterface $logger,
        int|null $connectionLimitPerIp,
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

        if ($client === null) {
            return null;
        }

        if ($this->onConnectCallback !== null) {
            ($this->onConnectCallback)($client);
        }

        $client->onClose(function () use ($socket, $client) {
            if ($this->onCloseCallback !== null) {
                ($this->onCloseCallback)($client);
            }
        });

        return $client;
    }
}
