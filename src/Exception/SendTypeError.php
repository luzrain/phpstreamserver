<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Exception;

final class SendTypeError extends \TypeError
{
    /**
     * @param class-string $class
     * @psalm-suppress PossiblyUndefinedArrayOffset
     */
    public function __construct(string $class, string $acceptType, string $givenType)
    {
        $trace = $this->getTrace()[0];
        $method = (new \ReflectionClass($class))->getMethod($trace['function']);
        $this->line = $trace['line'];
        $this->file = $trace['file'];
        $message = \sprintf(
            '%s::%s(): Argument #1 ($%s) must be of type %s, %s given',
            $class,
            $method->getName(),
            $method->getParameters()[0]->getName(),
            $acceptType,
            $givenType,
        );
        parent::__construct($message);
    }
}
