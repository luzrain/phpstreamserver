<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Exception;

final class EncodeTypeError extends \TypeError
{
    public function __construct(public readonly string $acceptType, public readonly string $givenType)
    {
        parent::__construct(\sprintf('Argument #2 ($buffer) must be of type %s, %s given', $this->acceptType, $this->givenType));
    }
}
