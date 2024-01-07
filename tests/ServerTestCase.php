<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Test;

use PHPUnit\Framework\TestCase;
use Revolt\EventLoop\Driver\StreamSelectDriver;

abstract class ServerTestCase extends TestCase
{
    /**
     * @return resource
     */
    protected function streamSocketClient(string $address): mixed
    {
        if ($fp = \stream_socket_client($address, $errno, $errstr, 3)) {
            \stream_set_blocking($fp, false);
            return $fp;
        }

        $this->fail($errstr);
    }

    /**
     * @param resource $fd
     * @param int $timeout Timeout in seconds
     */
    protected function fread(mixed $fd, int $timeout = 1): string
    {
        $eventLoop = new StreamSelectDriver();
        $suspension = $eventLoop->getSuspension();
        $eventLoop->onReadable($fd, fn() => $suspension->resume(\stream_get_contents($fd)));
        $eventLoop->delay($timeout, fn() => $suspension->resume(false));

        if ($data = $suspension->suspend()) {
            return $data;
        }

        $this->fail('Stream read failure');
    }
}
