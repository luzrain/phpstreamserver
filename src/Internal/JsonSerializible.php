<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Internal;

/**
 * @internal
 * @psalm-require-implements \JsonSerializable
 */
trait JsonSerializible
{
    /**
     * @psalm-suppress RawObjectIteration
     */
    public function jsonSerialize(): mixed
    {
        $toSnakeCase = static fn(string $str): string => \strtolower(\preg_replace('/[A-Z]/', '_\\0', \lcfirst($str)));
        $data = [];
        foreach ($this as $key => $val) {
            $value = match(true) {
                $val instanceof \DateTimeInterface => $val->format(\DateTimeInterface::ATOM),
                default => $val,
            };
            if ($value !== null) {
                $data[\is_string($key) ? $toSnakeCase($key) : $key] = $value;
            }
        }
        return $data;
    }
}
