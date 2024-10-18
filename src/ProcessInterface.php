<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Internal\Container;
use Psr\Log\LoggerInterface;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;

interface ProcessInterface extends MessageBus
{
    /**
     * Run process
     */
    public function run(Container $workerContainer): int;

    /**
     * Process name
     */
    public function getName(): string;

    /**
     * Monotonic process identifier (not pid)
     */
    public function getId(): int;

    /**
     * Process identifier (pid)
     */
    public function getPid(): int;

    /**
     * PSR logger
     */
    public function getLogger(): LoggerInterface;

    /**
     * Stop and destroy the process event loop and communication with the master process.
     * After the process is detached, only the basic supervisor will work for it.
     * This can be useful to give control to an external program and have it monitored by the master process.
     */
    public function detach(): void;

    /**
     * Give control to an external program
     *
     * @param string $path path to a binary executable or a script
     * @param array $args array of argument strings passed to the program
     * @see https://www.php.net/manual/en/function.pcntl-exec.php
     */
    public function exec(string $path, array $args = []): never;

    /**
     * Process user
     */
    public function getUser(): string;

    /**
     * Process group
     */
    public function getGroup(): string;
}
