<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://github.com/luzrain/phprunner/assets/25800964/b6d62fc9-d08b-4ac7-be0b-2da6653d4c6b">
    <img alt="PhpRunner logo" align="center" src="https://github.com/luzrain/phprunner/assets/25800964/9107aca7-13e0-40a9-b107-7dde99f171d1">
  </picture>
</p>

# PhpRunner - PHP Application Server
![PHP >=8.2](https://img.shields.io/badge/PHP->=8.2-777bb3.svg?style=flat)
[![Version](https://img.shields.io/github/v/tag/luzrain/phprunner?label=Version&filter=v*.*.*&sort=semver&color=374151)](../../releases)
[![Tests Status](https://img.shields.io/github/actions/workflow/status/luzrain/phprunner/tests.yaml?label=Tests&branch=master)](../../actions/workflows/tests.yaml)

PhpRunner is a high performance event-loop based process manager, TCP, and UDP server written in PHP.  
With a built-in PSR-7 HTTP server you can easily integrate any PSR-7 compatible framework with it in no time.  
The built-in HTTP server is memory efficient no matter how large your HTTP requests and responses you operate are.  
PhpRunner is supports TLS encryption and the ability to implement custom protocols.  

#### Key features:
- Supervisor;
- Workers lifecycle management (reload by TTL, MaxMemory, MaxRequests, OnException and more);
- PSR-7 HTTP server;

#### Requirements and limitations:  
 - Unix based OS (no windows support);
 - php-posix and php-pcntl extensions;
 - php-uv extension is not required, but highly recommended for better performance.

## Getting started
### Install composer packages
```bash
$ composer require luzrain/phprunner
```

### Configure server
Here is example of simple http server.
```php
// server.php

use Luzrain\PhpRunner\Exception\HttpException;
use Luzrain\PhpRunner\PhpRunner;
use Luzrain\PhpRunner\Server\Connection\ConnectionInterface;
use Luzrain\PhpRunner\Server\Http\Psr7\Response;
use Luzrain\PhpRunner\Server\Protocols\Http;
use Luzrain\PhpRunner\Server\Server;
use Psr\Http\Message\ServerRequestInterface;

$phpRunner = new PhpRunner();
$phpRunner->addWorkers(
    new WorkerProcess(
        name: 'HTTP Server',
        onStart: function (WorkerProcess $worker) {
            $worker->startServer(new Server(
                listen: 'tcp://0.0.0.0:80',
                protocol: new Http(),
                onMessage: function (ConnectionInterface $connection, ServerRequestInterface $data): void {
                    $response = match ($data->getUri()->getPath()) {
                        '/' => new Response(body: 'Hello world'),
                        '/ping' => new Response(body: 'pong'),
                        default => throw HttpException::createNotFoundException(),
                    };
                    $connection->send($response);
                },
            ));
        },
    ),
);
exit($phpRunner->run());
```

### Run
```bash
$ php server.php start
```
