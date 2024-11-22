<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer;

final readonly class FlattenObject
{
    private function __construct(
        public string $class,
    ) {
    }

    public static function create(object $object): self
    {
        return new self(self::parseAnonymousClass($object::class));
    }

    public function toString(): string
    {
        return \sprintf('[object(%s)]', $this->class);
    }

    private static function parseAnonymousClass(string $class): string
    {
        return \str_contains($class, "@anonymous\0")
            ? (\get_parent_class($class) ?: \key(\class_implements($class)) ?: 'class').'@anonymous'
            : $class
        ;
    }
}
