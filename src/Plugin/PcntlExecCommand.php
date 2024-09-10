<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin;

use Luzrain\PHPStreamServer\Server;

trait PcntlExecCommand
{
    /**
     * Prepare command for pcntl_exec acceptable format
     *
     * @return array{0: string, 1: list<string>}
     */
    private function prepareCommandForPcntlExec(string $command): array
    {
        // Check if command contains logic operators such as && and ||
        if (\preg_match('/(\'[^\']*\'|"[^"]*")(*SKIP)(*FAIL)|&&|\|\|/', $command) === 1) {
            throw new \RuntimeException(\sprintf(
                '%s does not directly support executing multiple commands with logical operators. Use shell with -c option e.g. "/bin/sh -c "%s"',
                Server::NAME,
                $command,
            ));
        }

        \preg_match_all('/\'[^\']*\'|"[^"]*"|\S+/', $command, $matches);
        $parts = \array_map(static fn (string $part): string => \trim($part, '"\''), $matches[0]);
        $binary = \array_shift($parts);
        $args = $parts;

        if (!\str_starts_with($binary, '/') && \is_string($absoluteBinaryPath = \shell_exec("command -v $binary"))) {
            $binary = \trim($absoluteBinaryPath);
        }

        return [$binary, $args];
    }
}
