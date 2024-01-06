<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Test;

use PHPUnit\Framework\TestCase;

final class ProcessManagerTest extends TestCase
{
    public function testWorkerProcessesStarted(): void
    {
        // Arrange
        $status = getServerStatus();
        $startedAt = new \DateTimeImmutable($status->started_at ?? 'now');
        $now = new \DateTimeImmutable('now');

        // Assert
        $this->assertTrue($status->is_running);
        $this->assertLessThanOrEqual(1, $now->getTimestamp() - $startedAt->getTimestamp());
        $this->assertSame(4, $status->workers_count);
        $this->assertSame(7, $status->processes_count);
    }

    public function testWorkerProcessRestartsAfterKill(): void
    {
        // Arrange
        $pids1 = \array_map(static fn ($p) => $p->pid, getServerStatus()->processes);
        $pidToKill = empty($pids1) ? null : $pids1[array_rand($pids1)];

        // Act
        $pidToKill !== null && \posix_kill($pidToKill, SIGTERM);
        $pids2 = \array_map(static fn ($p) => $p->pid, getServerStatus()->processes);

        // Assert
        $this->assertGreaterThan(0, \count($pids1));
        $this->assertSame(\count($pids1), \count($pids2));
        $this->assertNotContains($pidToKill, $pids2);
    }

    public function testReloadCommandReloadsAllWorkers(): void
    {
        // Arrange
        $pids1 = \array_map(static fn ($p) => $p->pid, getServerStatus()->processes);

        // Act
        \exec(getServerStartCommandLine('reload'));
        $pids2 = \array_map(static fn ($p) => $p->pid, getServerStatus()->processes);

        // Assert
        $this->assertGreaterThan(0, \count($pids1));
        $this->assertSame(\count($pids1), \count($pids2));
        $this->assertEmpty(\array_intersect($pids1, $pids2));
    }
}
