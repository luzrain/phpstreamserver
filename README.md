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

> [!NOTE]  
> This package is now under development

PHPStreamServer is a high performance event-loop based process manager, scheduler and webserver written in PHP.
This application server is designed to replace traditional setup for running php applications such as nginx, php-fpm, cron, supervisor.

#### Key features:
- Process manager;
- Scheduler;
- Workers lifecycle management (reload by TTL, max memory, max requests, on exception, on each request);
- HTTP/2

#### Requirements and limitations:  
 - Unix based OS (no windows support);
 - php-posix and php-pcntl extensions;
 - php-uv extension is not required, but recommended for better performance.

## Getting started
### Install composer packages
```bash
$ composer require luzrain/phpstreamserver
```

### Configure server
Here is example of simple http server.

```php
// server.php

use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\PeriodicProcess;
use Luzrain\PHPStreamServer\Plugin\HttpServer\HttpServer;
use Luzrain\PHPStreamServer\Plugin\HttpServer\HttpServerModule;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Listen;
use Luzrain\PHPStreamServer\Plugin\Scheduler\Scheduler;
use Luzrain\PHPStreamServer\Plugin\Supervisor\Supervisor;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\WorkerProcess;

$server = new Server();

$server->addPlugin(
    new HttpServer(
        name: 'web server',
        count: 1,
        listen: '0.0.0.0:8080',
        onStart: function (WorkerProcess $worker, mixed &$context): void {
            // initialization
        },
        onRequest: function (Request $request, mixed &$context): Response {
            return match ($request->getUri()->getPath()) {
                '/' => new Response(body: 'Hello world'),
                '/ping' => new Response(body: 'pong'),
                default => throw new HttpErrorException(404),
            };
        }
    ),
);

$server->addPlugin(
    new Scheduler(
        name: 'scheduled program',
        schedule: '*/1 * * * *',
        command: function (PeriodicProcess $worker): void {
            // runs every 1 minute
        },
    ),
);

$server->addPlugin(
    new Supervisor(
        name: 'supervised program',
        count: 1,
        command: function (WorkerProcess $worker): void {
            // custom long running process
        },
    ),
);

exit($server->run());
```

### Run
```bash
$ php server.php start
```
