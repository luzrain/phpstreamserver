<?php

declare(strict_types=1);

namespace PHPStreamServer\SchedulerPlugin\Trigger;

use Cron\CronExpression;

final class CronExpressionTrigger implements TriggerInterface
{
    private CronExpression $expression;

    public function __construct(string $expression)
    {
        if (!\class_exists(CronExpression::class)) {
            throw new \RuntimeException(\sprintf('You cannot use "%s" as the "cron expression" package is not installed. Try running "composer require dragonmantank/cron-expression".', __CLASS__));
        }

        try {
            $this->expression = new CronExpression($expression);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(\sprintf('Invalid cron expression "%s"', $expression), 0, $e);
        }
    }

    public function __toString(): string
    {
        return $this->expression->getExpression();
    }

    public function getNextRunDate(\DateTimeImmutable $now): \DateTimeImmutable|null
    {
        $next = $this->expression->getNextRunDate($now);

        if ($next instanceof \DateTime) {
            $next = \DateTimeImmutable::createFromMutable($next);
        }

        return $next;
    }
}
