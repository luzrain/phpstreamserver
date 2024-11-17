<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Composer\InstalledVersions;
use Luzrain\PHPStreamServer\Internal\Console\App;
use Luzrain\PHPStreamServer\Plugin\Supervisor\SupervisorPlugin;
use Luzrain\PHPStreamServer\Plugin\System\SystemPlugin;
use Revolt\EventLoop;
use function Luzrain\PHPStreamServer\Internal\getDefaultPidFile;
use function Luzrain\PHPStreamServer\Internal\getDefaultSocketFile;

final class Server
{
    public const PACKAGE = 'luzrain/phpstreamserver';
    public const NAME = 'PHPStreamServer';
    public const SHORTNAME = 'phpss';
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
        $this->pidFile ??= getDefaultPidFile();
        $this->socketFile ??= getDefaultSocketFile();
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
        $app = new App(...\array_merge(...\array_map(static fn (Plugin $p) => $p->registerCommands(), $this->plugins)));
        $map = new \WeakMap();
        $map[EventLoop::getDriver()] = \get_object_vars($this);
        unset($this->workers, $this->plugins);
        return $app->run($map);
    }

    public static function getVersion(): string
    {
        static $version;
        return $version ??= InstalledVersions::getVersion(self::PACKAGE);
    }

    public static function getVersionString(): string
    {
        return \sprintf('%s/%s', \strtolower(self::NAME), self::getVersion());
    }
}
