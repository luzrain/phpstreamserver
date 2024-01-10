<?php

declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

use Luzrain\PhpRunner\Exception\HttpException;
use Luzrain\PhpRunner\PhpRunner;
use Luzrain\PhpRunner\Server\Connection\ConnectionInterface;
use Luzrain\PhpRunner\Server\Protocols\Http;
use Luzrain\PhpRunner\Server\Protocols\Raw;
use Luzrain\PhpRunner\Server\Protocols\Text;
use Luzrain\PhpRunner\Server\Server;
use Luzrain\PhpRunner\WorkerProcess;
use Psr\Http\Message\UploadedFileInterface;

$phpRunner = new PhpRunner();
$phpRunner->addWorkers(
    new WorkerProcess(
        name: 'Worker 1',
        count: 1,
    ),
    new WorkerProcess(
        name: 'Worker 2',
        count: 2,
    ),
    new WorkerProcess(
        name: 'HTTP Server',
        count: 2,
        server: new Server(
            listen: 'tcp://0.0.0.0:9080',
            protocol: new Http(),
            onMessage: function (ConnectionInterface $connection, \Nyholm\Psr7\ServerRequest $data): void {
                $files = $data->getUploadedFiles();
                \array_walk_recursive($files, static function(UploadedFileInterface &$file) {
                    $file = [
                        'client_filename' => $file->getClientFilename(),
                        'client_media_type' => $file->getClientMediaType(),
                        'size' => $file->getSize(),
                        'sha1' => \hash('sha1', $file->getStream()->getContents()),
                    ];
                });
                $response = match ($data->getUri()->getPath()) {
                    '/ok' => new \Nyholm\Psr7\Response(
                        status: 200,
                        headers: ['Content-Type' => 'text/plain'],
                        body: 'ok-answer',
                    ),
                    '/request' => new \Nyholm\Psr7\Response(
                        status: 200,
                        headers: ['Content-Type' => 'application/json'],
                        body: \json_encode([
                            'server_params' => $data->getServerParams(),
                            'headers' => $data->getHeaders(),
                            'query' => $data->getQueryParams(),
                            'request' => $data->getParsedBody(),
                            'files' => $files,
                            'cookies' => $data->getCookieParams(),
                            'raw_request' => empty($files) ? $data->getBody()->getContents() : '',
                        ]),
                    ),
                    default => throw HttpException::createNotFoundException(),
                };
                $connection->send($response);
            },
        ),
    ),
    new WorkerProcess(
        name: 'HTTPS Server',
        count: 1,
        server: new Server(
            listen: 'tcp://0.0.0.0:9081',
            tls: true,
            tlsCertificate: __DIR__ . '/localhost.crt',
            protocol: new Http(),
            onMessage: function (ConnectionInterface $connection, \Nyholm\Psr7\ServerRequest $data): void {
                $connection->send(new \Nyholm\Psr7\Response(
                    status: 200,
                    headers: ['Content-Type' => 'text/plain'],
                    body: 'ok-answer-tls',
                ));
            },
        ),
    ),
    new WorkerProcess(
        name: 'TCP TEXT Server',
        count: 1,
        server: new Server(
            listen: 'tcp://0.0.0.0:9082',
            protocol: new Text(),
            onMessage: function (ConnectionInterface $connection, string $data): void {
                $connection->send('echo:' . $data);
            },
        ),
    ),
    new WorkerProcess(
        name: 'UDP TEXT Server',
        count: 1,
        server: new Server(
            listen: 'udp://0.0.0.0:9083',
            protocol: new Text(),
            onMessage: function (ConnectionInterface $connection, string $data): void {
                $connection->send('echo:' . $data);
            },
        ),
    ),
    new WorkerProcess(
        name: 'TCP RAW Server',
        count: 1,
        server: new Server(
            listen: 'tcp://0.0.0.0:9084',
            protocol: new Raw(),
            onMessage: function (ConnectionInterface $connection, string $data): void {
                $connection->send('echo:' . $data);
            },
        ),
    ),
    new WorkerProcess(
        name: 'UDP RAW Server',
        count: 1,
        server: new Server(
            listen: 'udp://0.0.0.0:9085',
            protocol: new Raw(),
            onMessage: function (ConnectionInterface $connection, string $data): void {
                $connection->send('echo:' . $data);
            },
        ),
    ),
);
exit($phpRunner->run());
