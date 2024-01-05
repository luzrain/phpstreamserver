<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Status;

use Luzrain\PhpRunner\Internal\JsonSerializible;
use Luzrain\PhpRunner\PhpRunner;
use Revolt\EventLoop\DriverFactory;

/**
 * @internal
 */
final readonly class MasterProcessStatus implements \JsonSerializable
{
    use JsonSerializible;

    public string $phpVersion;
    public string $phpRunnerVersion;
    public string $eventLoop;
    public int $totalMemory;
    public int $workersCount;
    public int $processesCount;

    /**
     * @param list<WorkerStatus> $workers
     * @param list<WorkerProcessStatus> $processes
     */
    public function __construct(
        public int $pid,
        public string $user,
        public int $memory,
        public \DateTimeImmutable|null $startedAt,
        public bool $isRunning,
        public string $startFile,
        public array $workers,
        public array $processes = [],
    ) {
        $eventLoop = (new DriverFactory())->create();
        $eventLoopName = (new \ReflectionObject($eventLoop))->getShortName();

        $this->phpVersion = PHP_VERSION;
        $this->phpRunnerVersion = PhpRunner::VERSION;
        $this->eventLoop = $eventLoopName;
        $this->totalMemory = \array_sum(\array_map(fn(WorkerProcessStatus $p) => $p->memory, $this->processes));
        $this->workersCount = \count($this->workers);
        $this->processesCount = \count($this->processes);
    }
}
