# PHPRunner - PHP Application Server
![PHP >=8.2](https://img.shields.io/badge/PHP-^8.2-777bb3.svg?style=flat)
[![Tests Status](https://img.shields.io/github/actions/workflow/status/luzrain/phprunner/tests.yaml?branch=master)](../../actions/workflows/tests.yaml)

PHPRunner is a high performance process manager, TCP, and UDP server written in PHP.  
With a built-in PSR-7 compatible HTTP server implementation you can easily integrate any PSR-7 compatible framework with it in no time.  
The built-in HTTP server is memory efficient no matter how large your HTTP requests and responses you operate are.  
PHPRunner is supports TLS encryption and the ability to implement custom protocols.  

#### Key features:
- Supervisor;
- PSR-7 compatible HTTP server implementation;
- Memory efficiency.

#### Requirements and limitations:  
 - Unix based OS (no windows support);
 - POSIX and PCNTL extensions;
 - UV extension is not required, but highly recommended for better performance, especially in production environments.

## Getting started
### Install composer packages
```bash
$ composer require luzrain/phprunner @TODO
```

### Configure and run server
Here is example of simple http server.  
See more examples in ...
```php

use Luzrain\PhpRunner\Exception\HttpException;
use Luzrain\PhpRunner\PhpRunner;
use Luzrain\PhpRunner\Server\Connection\ConnectionInterface;
use Luzrain\PhpRunner\Server\Protocols\Http;
use Luzrain\PhpRunner\Server\Server;
use Luzrain\PhpRunner\WorkerProcess;

$phpRunner = new PhpRunner();
$phpRunner->addWorkers(
    new WorkerProcess(
        name: 'HTTP Server',
        count: 2,
        server: new Server(
            listen: 'tcp://0.0.0.0:80',
            protocol: new Http(),
            onMessage: function (ConnectionInterface $connection, \Nyholm\Psr7\ServerRequest $data): void {
                $response = match ($data->getUri()->getPath()) {
                    '/ping' => new \Nyholm\Psr7\Response(
                        status: 200,
                        headers: ['Content-Type' => 'text/plain'],
                        body: 'pong',
                    ),
                    default => throw HttpException::createNotFoundException(),
                };
                $connection->send($response);
            },
        ),
    ),
);
exit($phpRunner->run());
```
