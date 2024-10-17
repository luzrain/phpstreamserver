<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

/**
 * @internal
 */
final class ContextNormalizer
{
    public function __construct()
    {
    }

    public function normalize(mixed $data): mixed
    {
        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->normalize($value);
            }

            return $data;
        }

        if (\is_null($data) || \is_scalar($data)) {
            return $data;
        }

        if ($data instanceof \Throwable) {
            return $this->normalizeException($data);
        }

        if ($data instanceof \JsonSerializable) {
            return $data->jsonSerialize();
        }

        if ($data instanceof \Stringable) {
            return $data->__toString();
        }

        if ($data instanceof \DateTimeInterface) {
            return $data->format(\DateTimeInterface::RFC3339);
        }

        if (\is_object($data)) {
            return \sprintf('[object(%s)]', $data::class);
        }

        if (\is_resource($data)) {
            return \sprintf('[resource(%s)]', \get_resource_type($data));
        }

        return \sprintf('[unknown(%s)]', \get_debug_type($data));
    }

    private function normalizeException(\Throwable $e): string
    {
        $str = $this->formatException($e, 'object');

        if (null !== $previous = $e->getPrevious()) {
            do {
                $str .= "\n" . $this->formatException($previous, 'previous');
            } while (null !== $previous = $previous->getPrevious());
        }

        return $str;
    }

    private function formatException(\Throwable $e, string $objectTitle): string
    {
        return \sprintf(
            "[%s(%s) code:%d]: %s at %s:%d",
            $objectTitle,
            $e::class,
            $e->getCode(),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        );
    }
}
