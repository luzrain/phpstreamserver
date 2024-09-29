<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer\Internal;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Driver\ClientFactory;
use Amp\Socket\Socket;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\NetworkTrafficCounter;

final readonly class TrafficCountingClientFactory implements ClientFactory
{
    public function __construct(
        private ClientFactory $clientFactory,
        private NetworkTrafficCounter $trafficStatisticStore,
    ) {
    }

    public function createClient(Socket $socket): Client|null
    {
        $client = $this->clientFactory->createClient($socket);

        if ($client === null) {
            return null;
        }

        $this->trafficStatisticStore->addConnection($socket);

        $client->onClose(function () use ($socket) {
            $this->trafficStatisticStore->removeConnection($socket);
        });

        return $client;
    }
}
