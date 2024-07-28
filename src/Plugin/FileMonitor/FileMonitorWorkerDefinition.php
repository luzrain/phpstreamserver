<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\FileMonitor;

use Luzrain\PHPStreamServer\Plugin\FileMonitor\FileMonitorWatcher\FileMonitorWatcher;
use Luzrain\PHPStreamServer\WorkerProcessDefinition;
use Luzrain\PHPStreamServer\WorkerProcessInterface;

final class FileMonitorWorkerDefinition extends WorkerProcessDefinition
{
    public function __construct(
        private array $sourceDir,
        private array $filePattern,
        private float $pollingInterval,
        string|null $user,
        string|null $group,
        private \Closure $reloadCallback,
    ) {
        parent::__construct(
            name: 'File monitor',
            user: $user,
            group: $group,
            reloadable: false,
            onStart: $this->onStart(...),
        );
    }

    private function onStart(WorkerProcessInterface $worker): void
    {
        $fileMonitor = FileMonitorWatcher::create(
            $worker->getLogger(),
            $this->sourceDir,
            $this->filePattern,
            $this->pollingInterval,
            $this->doReload(...),
        );
        $fileMonitor->start();
    }

    /**
     * @psalm-suppress NoValue
     * @psalm-suppress RiskyTruthyFalsyComparison
     */
    private function doReload(): void
    {
        ($this->reloadCallback)();

        if (\function_exists('opcache_get_status') && $status = \opcache_get_status()) {
            foreach (\array_keys($status['scripts'] ?? []) as $file) {
                \opcache_invalidate($file, true);
            }
        }
    }
}
