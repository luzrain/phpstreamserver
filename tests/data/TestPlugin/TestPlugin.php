<?php

declare(strict_types=1);

namespace PHPStreamServer\Test\data\TestPlugin;

use PHPStreamServer\Core\Plugin\Plugin;

final class TestPlugin extends Plugin
{
    public function registerCommands(): array
    {
        return [
            new TestDispatchCommand(),
        ];
    }
}
