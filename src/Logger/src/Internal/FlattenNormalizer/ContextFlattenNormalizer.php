<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer;

/**
 * @internal
 */
final class ContextFlattenNormalizer
{
    private function __construct()
    {
    }

    public static function flatten(mixed $data): mixed
    {
        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::flatten($value);
            }

            return $data;
        }

        if (\is_null($data) || \is_scalar($data)
            || $data instanceof FlattenException
            || $data instanceof FlattenDateTime
            || $data instanceof FlattenObject
            || $data instanceof FlattenResource
            || $data instanceof FlattenEnum
        ) {
            return $data;
        }

        if ($data instanceof \Throwable) {
            return FlattenException::create($data);
        }

        if ($data instanceof \DateTimeInterface) {
            return FlattenDateTime::create($data);
        }

        if ($data instanceof \JsonSerializable) {
            return self::flatten($data->jsonSerialize());
        }

        if ($data instanceof \Stringable) {
            return $data->__toString();
        }

        if ($data instanceof \UnitEnum) {
            return FlattenEnum::create($data);
        }

        if (\is_object($data)) {
            return FlattenObject::create($data);
        }

        if (\is_resource($data)) {
            return FlattenResource::create($data);
        }

        return \sprintf('unknown(%s)', \get_debug_type($data));
    }
}
