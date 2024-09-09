<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Amp\Future;
use Luzrain\PHPStreamServer\Exception\UserChangeException;
use Luzrain\PHPStreamServer\Internal\MessageBus\Message;
use Luzrain\PHPStreamServer\ProcessInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;

/**
 * @internal
 * @psalm-require-implements ProcessInterface
 */
trait ProcessTrait
{
    private readonly string $name;
    private readonly int $id;
    private readonly int $pid;
    private string|null $user = null;
    private string|null $group = null;
    private int $exitCode = 0;
    private LoggerInterface $logger;
    private string $socketFile;

    public function run(WorkerContext $workerContext): int
    {
        $this->logger = $workerContext->logger;
        $this->socketFile = $workerContext->socketFile;
        $this->setUserAndGroup();
        $this->initWorker();
        EventLoop::run();

        return $this->exitCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    private function setUserAndGroup(): void
    {
        try {
            Functions::setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $refl = new \ReflectionObject($this);
            $this->logger->warning($e->getMessage(), [$refl->getShortName() => $this->getName()]);
            $this->user = Functions::getCurrentUser();
        }
    }

    public function detach(): void
    {
        $identifiers = EventLoop::getDriver()->getIdentifiers();
        \array_walk($identifiers, EventLoop::getDriver()->cancel(...));
        EventLoop::getDriver()->stop();
        unset($this->logger);
        unset($this->socketFile);
    }

    public function exec(string $path, array $args = []): never
    {
        $this->detach();
        $envVars = [...\getenv(), ...$_ENV];
        \pcntl_exec($path, $args, $envVars);
        exit(0);
    }

    public function getUser(): string
    {
        return $this->user ?? Functions::getCurrentUser();
    }

    public function getGroup(): string
    {
        return $this->group ?? Functions::getCurrentGroup();
    }

    /**
     * @template T
     * @param Message<T> $message
     * @return Future<T>
     */
    public function dispatch(Message $message): Future
    {
        return $this->messageBus->dispatch($message);
    }
}
