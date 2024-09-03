<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\FileMonitor;

use Amp\Future;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\Module;
use function Amp\async;

final readonly class FileMonitor implements Module
{
    public function __construct(
        private string $sourceDir,
        private array $filePattern = ['*'],
    ) {
        if (!\function_exists('inotify_init')) {
            throw new \LogicException(\sprintf(
                'You cannot use "%s" as the "inotify" extension is not installed. Try running "composer require luzrain/polyfill-inotify" or install "inotify" extension.',
                __CLASS__
            ));
        }
    }

    public function start(MasterProcess $masterProcess): void
    {
        $fileMonitor = new Internal\InotifyMonitorWatcher(
            sourceDir: $this->sourceDir,
            filePattern: $this->filePattern,
            reloadCallback: function () use ($masterProcess): void {
                $masterProcess->reload();
                $this->opcacheInvalidate();
            },
        );

        $fileMonitor->start();
    }

    public function stop(): Future
    {
        return async(static fn() => null);
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
