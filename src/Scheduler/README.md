<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_scheduler_light.svg">
    <img alt="PHPStreamServer logo" align="center" width="70%" src="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_scheduler_dark.svg">
  </picture>
</p>

# Scheduler Plugin for PHPStreamServer
![PHP >=8.2](https://img.shields.io/badge/PHP->=8.2-777bb3.svg)
![Version](https://img.shields.io/github/v/tag/phpstreamserver/phpstreamserver?label=Version&filter=v*.*.*&sort=semver&color=374151)

The Scheduler Plugin for **PHPStreamServer** extends the core functionality by providing a scheduling capability,
allowing you to run programs or tasks at specific intervals, much like a traditional cron job.

## Features:
 - Defining schedules using cron syntax.
 - Defining schedules using relative date format as supported by \DateInterval.

## Getting started
### Install composer packages
```bash
$ composer require phpstreamserver/core phpstreamserver/scheduler
```

### Configure server
Here is an example of a simple server configuration with scheduler.

```php
// server.php

use PHPStreamServer\Core\Server;
use PHPStreamServer\Plugin\Scheduler\PeriodicProcess;
use PHPStreamServer\Plugin\Scheduler\SchedulerPlugin;

$server = new Server();

$server->addPlugin(
    new SchedulerPlugin(),
);

$server->addWorker(
    new PeriodicProcess(
        name: 'Scheduled process',
        schedule: '*/1 * * * *',
        onStart: function (PeriodicProcess $worker): void {
            // runs every 1 minute
        },
    ),
);

exit($server->run());
```

### Run
```bash
$ php server.php start
```
