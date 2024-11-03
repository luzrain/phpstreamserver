<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\FileMonitor;

use Luzrain\PHPStreamServer\BundledPlugin\FileMonitor\Internal\InotifyMonitorWatcher;
use Luzrain\PHPStreamServer\MessageBus\Message\ReloadServerCommand;
use Luzrain\PHPStreamServer\MessageBus\MessageBusInterface;
use Luzrain\PHPStreamServer\Plugin;

final class FileMonitor extends Plugin
{
    private MessageBusInterface $messageBus;
    private array $watchDirs;

    public function __construct(WatchDir ...$watch)
    {
        $this->watchDirs = $watch;
    }

    public function start(): void
    {
        /** @var MessageBusInterface */
        $this->messageBus = &$this->masterContainer->get('bus');

        foreach ($this->watchDirs as $watchDir) {
            $fileMonitor = new InotifyMonitorWatcher(
                sourceDir: $watchDir->sourceDir,
                filePattern: $watchDir->filePattern,
                reloadCallback: $watchDir->invalidateOpcache
                    ? $this->triggerReloadWithOpcacheReset(...)
                    : $this->triggerReloadWithoutOpcacheReset(...),
            );

            $fileMonitor->start();
        }
    }

    private function triggerReloadWithoutOpcacheReset(): void
    {
        $this->messageBus->dispatch(new ReloadServerCommand());
    }

    private function triggerReloadWithOpcacheReset(): void
    {
        $this->messageBus->dispatch(new ReloadServerCommand());

        if (\function_exists('opcache_get_status') && $status = \opcache_get_status()) {
            foreach (\array_keys($status['scripts'] ?? []) as $file) {
                \opcache_invalidate($file, true);
            }
        }
    }
}
