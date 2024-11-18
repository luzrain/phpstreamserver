<?php

declare(strict_types=1);

namespace PHPStreamServer\FileMonitorPlugin;

use PHPStreamServer\FileMonitorPlugin\Internal\InotifyMonitorWatcher;
use PHPStreamServer\MessageBus\Message\ReloadServerCommand;
use PHPStreamServer\MessageBus\MessageBusInterface;
use PHPStreamServer\Plugin;

final class FileMonitor extends Plugin
{
    private MessageBusInterface $messageBus;
    private array $watchDirs;

    public function __construct(WatchDir ...$watch)
    {
        $this->watchDirs = $watch;
    }

    public function onStart(): void
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
