<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Exception;

use Luzrain\PhpRunner\Internal\Functions;

final class TooLargePayload extends \Exception
{
    public function __construct(int $maxPayloadBytes)
    {
        parent::__construct('The request is larger than ' . Functions::humanFileSize($maxPayloadBytes));
    }
}
