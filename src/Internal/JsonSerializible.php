<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Internal;

/**
 * @psalm-require-implements \JsonSerializable
 */
trait JsonSerializible
{
    public function jsonSerialize(): mixed
    {
        $toSnakeCase = static fn(string $str): string => \strtolower(\preg_replace('/[A-Z]/', '_\\0', \lcfirst($str)));
        $data = [];
        foreach ($this as $key => $val) {
            $data[\is_string($key) ? $toSnakeCase($key) : $key] = match(true) {
                $val instanceof \DateTimeInterface => $val->format(\DateTimeInterface::ATOM),
                default => $val,
            };
        }
        return $data;
    }
}
