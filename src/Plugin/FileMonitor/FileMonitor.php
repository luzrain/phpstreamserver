<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\FileMonitor;

use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\Plugin;

final class FileMonitor extends Plugin
{
    private \Closure $reload;

    public function __construct(
        private string $sourceDir,
        private array $filePattern = ['*'],
    ) {
    }

    public function init(MasterProcess $masterProcess): void
    {
        $this->reload = $masterProcess->reload(...);
    }

    public function start(): void
    {
        $fileMonitor = new Internal\InotifyMonitorWatcher(
            sourceDir: $this->sourceDir,
            filePattern: $this->filePattern,
            reloadCallback: function (): void {
                ($this->reload)();
                $this->opcacheInvalidate();
            },
        );

        $fileMonitor->start();
    }

    private function opcacheInvalidate(): void
    {
        if (\function_exists('opcache_get_status') && $status = \opcache_get_status()) {
            foreach (\array_keys($status['scripts'] ?? []) as $file) {
                \opcache_invalidate($file, true);
            }
        }
    }
}
