<?php

declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

use Luzrain\PhpRunner\Exception\HttpException;
use Luzrain\PhpRunner\PhpRunner;
use Luzrain\PhpRunner\Server\Connection\ConnectionInterface;
use Luzrain\PhpRunner\Server\Http\Psr7\Response;
use Luzrain\PhpRunner\Server\Protocols\Http;
use Luzrain\PhpRunner\Server\Protocols\Raw;
use Luzrain\PhpRunner\Server\Protocols\Text;
use Luzrain\PhpRunner\Server\Server;
use Luzrain\PhpRunner\WorkerProcess;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

$tempFiles = [];
$streamResponse = \fopen('php://temp', 'rw');
\fwrite($streamResponse, 'ok-answer from stream');
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
        onStart: function (WorkerProcess $worker) use (&$tempFiles, $streamResponse) {
            $worker->startServer(new Server(
                listen: 'tcp://0.0.0.0:9080',
                protocol: new Http(),
                onClose: function () use (&$tempFiles) {
                    foreach ($tempFiles as $tempFile) {
                        \is_file($tempFile) && \unlink($tempFile);
                    }
                },
                onMessage: function (ConnectionInterface $connection, ServerRequestInterface $data) use (&$tempFiles, $streamResponse): void {
                    $files = $data->getUploadedFiles();
                    \array_walk_recursive($files, static function (UploadedFileInterface &$file) use (&$tempFiles) {
                        $tmpFile = \sys_get_temp_dir() . '/' . \uniqid('test');
                        $tempFiles[] = $tmpFile;
                        $file->moveTo($tmpFile);
                        $file = [
                            'client_filename' => $file->getClientFilename(),
                            'client_media_type' => $file->getClientMediaType(),
                            'size' => $file->getSize(),
                            'sha1' => \hash_file('sha1', $tmpFile),
                        ];
                    });
                    $response = match ($data->getUri()->getPath()) {
                        '/ok1' => new Response(
                            body: 'ok-answer',
                            headers: ['Content-Type' => 'text/plain'],
                        ),
                        '/ok2' => new Response(
                            body: $streamResponse,
                            headers: ['Content-Type' => 'text/plain'],
                        ),
                        '/request' => new Response(
                            body: \json_encode([
                                'server_params' => $data->getServerParams(),
                                'headers' => $data->getHeaders(),
                                'query' => $data->getQueryParams(),
                                'request' => $data->getParsedBody(),
                                'files' => $files,
                                'cookies' => $data->getCookieParams(),
                                'raw_request' => empty($files) ? $data->getBody()->getContents() : '',
                            ]),
                            headers: ['Content-Type' => 'application/json'],
                        ),
                        default => throw HttpException::createNotFoundException(),
                    };
                    $connection->send($response);
                },
            ));
        },
    ),
    new WorkerProcess(
        name: 'HTTPS Server',
        count: 1,
        onStart: function (WorkerProcess $worker) {
            $worker->startServer(new Server(
                listen: 'tcp://127.0.0.1:9081',
                tls: true,
                tlsCertificate: __DIR__ . '/localhost.crt',
                protocol: new Http(),
                onMessage: function (ConnectionInterface $connection, ServerRequestInterface $data): void {
                    $connection->send(new Response(
                        body: 'ok-answer-tls',
                        headers: ['Content-Type' => 'text/plain'],
                    ));
                },
            ));
        },
    ),
    new WorkerProcess(
        name: 'TCP TEXT Server',
        count: 1,
        onStart: function (WorkerProcess $worker) {
            $worker->startServer(new Server(
                listen: 'tcp://127.0.0.1:9082',
                protocol: new Text(),
                onMessage: function (ConnectionInterface $connection, string $data): void {
                    $connection->send('echo:' . $data);
                },
            ));
        },
    ),
    new WorkerProcess(
        name: 'UDP TEXT Server',
        count: 1,
        onStart: function (WorkerProcess $worker) {
            $worker->startServer(new Server(
                listen: 'udp://127.0.0.1:9083',
                protocol: new Text(),
                onMessage: function (ConnectionInterface $connection, string $data): void {
                    $connection->send('echo:' . $data);
                },
            ));
        },
    ),
    new WorkerProcess(
        name: 'TCP RAW Server',
        count: 1,
        onStart: function (WorkerProcess $worker) {
            $worker->startServer(new Server(
                listen: 'tcp://127.0.0.1:9084',
                protocol: new Raw(),
                onMessage: function (ConnectionInterface $connection, string $data): void {
                    $connection->send('echo:' . $data);
                },
            ));
        },
    ),
    new WorkerProcess(
        name: 'UDP RAW Server',
        count: 1,
        onStart: function (WorkerProcess $worker) {
            $worker->startServer(new Server(
                listen: 'udp://127.0.0.1:9085',
                protocol: new Raw(),
                onMessage: function (ConnectionInterface $connection, string $data): void {
                    $connection->send('echo:' . $data);
                },
            ));
        },
    ),
);
exit($phpRunner->run());
