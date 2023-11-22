<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Status;

use Luzrain\PhpRunner\PhpRunner;
use Revolt\EventLoop\DriverFactory;

final class Status
{
    private const STATUS_RUNNING = 'running';
    private const STATUS_SHUTDOWN = 'stopped';

    public function __construct()
    {
    }

    /**
     * @return array{
     *     php_version: string,
     *     phprunner_version: string,
     *     event_loop: string,
     *     status: string
     * }
     */
    public function getData(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'phprunner_version' => PhpRunner::VERSION,
            'event_loop' => ((new DriverFactory())->create())::class,
            'status' => self::STATUS_SHUTDOWN,
        ];
    }
}
