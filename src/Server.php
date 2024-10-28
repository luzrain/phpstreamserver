<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\SupervisorPlugin;
use Luzrain\PHPStreamServer\BundledPlugin\System\SystemPlugin;
use Luzrain\PHPStreamServer\Internal\Console\App;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Plugin\Plugin;

final class Server
{
    public const VERSION = '0.2.2';
    public const VERSION_STRING = 'phpstreamserver/' . self::VERSION;
    public const NAME = 'PHPStreamServer';
    public const TITLE = '🌸 PHPStreamServer - PHP application server';

    /** @var array<Plugin> */
    private array $plugins = [];
    /** @var array<Process> */
    private array $workers = [];

    public function __construct(
        private string|null $pidFile = null,
        private string|null $socketFile = null,
        int $stopTimeout = 10,
        float $restartDelay = 0.25,
    ) {
        $this->pidFile ??= Functions::getDefaultPidFile();
        $this->socketFile ??= Functions::getDefaultSocketFile();
        $this->addPlugin(new SystemPlugin());
        $this->addPlugin(new SupervisorPlugin($stopTimeout, $restartDelay));
    }

    public function addPlugin(Plugin ...$plugins): self
    {
        \array_push($this->plugins, ...$plugins);

        return $this;
    }

    public function addWorker(Process ...$workers): self
    {
        \array_push($this->workers, ...$workers);

        return $this;
    }

    public function run(): int
    {
        $commands = \array_merge(...\array_map(static fn (Plugin $p) => $p->commands(), $this->plugins));
        $app = new App(...$commands);

        return $app->run(\get_object_vars($this));
    }
}
