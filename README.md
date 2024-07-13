<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://github.com/luzrain/phprunner/assets/25800964/3e1bb7da-5fa8-47cf-8c95-5454b8b5959f">
    <img alt="PHPStreamServer logo" align="center" width="70%" src="https://github.com/luzrain/phprunner/assets/25800964/5664f293-41a5-424e-9f52-a403b222b17d">
  </picture>
</p>

# PHPStreamServer - PHP Application Server
![PHP >=8.2](https://img.shields.io/badge/PHP->=8.2-777bb3.svg?style=flat)
[![Version](https://img.shields.io/github/v/tag/luzrain/phpstreamserver?label=Version&filter=v*.*.*&sort=semver&color=374151)](../../releases)
[![Tests Status](https://img.shields.io/github/actions/workflow/status/luzrain/phpstreamserver/tests.yaml?label=Tests&branch=master)](../../actions/workflows/tests.yaml)

PHPStreamServer is a high performance event-loop based process manager, TCP, and UDP server written in PHP.  
With a built-in PSR-7 HTTP server you can easily integrate any PSR-7 compatible framework with it in no time.  
The built-in HTTP server is memory efficient no matter how large your HTTP requests and responses you operate are.  
PHPStreamServer is supports TLS encryption and the ability to implement custom protocols.  

#### Key features:
- Supervisor;
- Workers lifecycle management (reload by TTL, max memory, max requests, on exception, on each request);
- PSR-7 HTTP server;

#### Requirements and limitations:  
 - Unix based OS (no windows support);
 - php-posix and php-pcntl extensions;
 - php-uv extension is not required, but highly recommended for better performance.

## Getting started
### Install composer packages
```bash
$ composer require luzrain/phpstreamserver
```

### Configure server
Here is example of simple http server.

```php
// server.php

use Luzrain\PHPStreamServer\Exception\HttpException;
use Luzrain\PHPStreamServer\Listener;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\Server\Connection\ConnectionInterface;
use Luzrain\PHPStreamServer\Server\Http\Psr7\Response;
use Luzrain\PHPStreamServer\Server\Protocols\Http;
use Luzrain\PHPStreamServer\WorkerProcess;
use Psr\Http\Message\ServerRequestInterface;

$server = new Server();
$server->addWorkersProcess(new WorkerProcess(
    name: 'HTTP Server',
    onStart: function (WorkerProcess $worker) {
        $worker->startListener(new Listener(
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
));

exit($server->run());
```

### Run
```bash
$ php server.php start
```
