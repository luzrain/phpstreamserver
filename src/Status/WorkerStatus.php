<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Status;

use Luzrain\PhpRunner\Internal\JsonSerializible;

/**
 * @internal
 */
final readonly class WorkerStatus implements \JsonSerializable
{
    use JsonSerializible;

    public function __construct(
        public string $user,
        public string $name,
        public int $count,
    ) {
    }
}
