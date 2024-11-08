<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;

use Amp\Future;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Formatter\GelfFormatter;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\FormatterInterface;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\GelfTransport\GelfHttpTransport;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\GelfTransport\GelfTcpTransport;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\GelfTransport\GelfTransport;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\GelfTransport\GelfUdpTransport;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogEntry;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogLevel;
use Revolt\EventLoop;
use function Amp\async;

final class GelfHandler extends Handler
{
    private FormatterInterface $formatter;
    private GelfTransport $transport;

    /**
     * @param string $address gelf address. Can start with udp:// tcp://, http:// or https://
     */
    public function __construct(
        string $address,
        string|null $hostName = null,
        bool $includeStacktraces = false,
        LogLevel $level = LogLevel::DEBUG,
        array $channels = [],
    ) {
        [$scheme, $host, $port] = $this->parseAddress($address);
        $this->formatter = new GelfFormatter($hostName, $includeStacktraces);
        $this->transport = match ($scheme) {
            'udp' => new GelfUdpTransport($host, $port),
            'tcp' => new GelfTcpTransport($host, $port),
            'http', 'https' => new GelfHttpTransport($address),
        };
        parent::__construct($level, $channels);
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function parseAddress(string $address): array
    {
        if (
            !\str_starts_with($address, 'udp://') &&
            !\str_starts_with($address, 'tcp://') &&
            !\str_starts_with($address, 'http://') &&
            !\str_starts_with($address, 'https://')
        ) {
            throw new \InvalidArgumentException('Address should start with "udp://", "tcp://", "http://" or "https://"');
        }

        $parts = \parse_url($address);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Invalid address format');
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = $parts['port'] ?? match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => throw new \InvalidArgumentException('Address should contain port'),
        };

        return [$scheme, $host, $port];
    }

    public function start(): Future
    {
        return async(function () {
            $this->transport->start();
        });
    }

    public function handle(LogEntry $record): void
    {
        EventLoop::queue(function () use ($record) {
            $this->transport->write($this->formatter->format($record));
        });
    }
}
