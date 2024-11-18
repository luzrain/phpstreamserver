<?php

declare(strict_types=1);

namespace PHPStreamServer\FileMonitorPlugin;

final readonly class WatchDir
{
    public function __construct(
        public string $sourceDir,
        public array $filePattern = ['*'],
        public bool $invalidateOpcache = false,
    ) {
    }
}
