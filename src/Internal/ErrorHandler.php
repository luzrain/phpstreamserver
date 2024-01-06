<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Internal;

use Luzrain\PhpRunner\Exception\HttpException;
use Luzrain\PhpRunner\Exception\TlsHandshakeException;
use Luzrain\PhpRunner\Exception\TooLargePayload;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * @internal
 */
final class ErrorHandler
{
    private const ERRORS = [
        \E_DEPRECATED => ['Deprecated', LogLevel::INFO],
        \E_USER_DEPRECATED => ['User Deprecated', LogLevel::INFO],
        \E_NOTICE => ['Notice', LogLevel::WARNING],
        \E_USER_NOTICE => ['User Notice', LogLevel::WARNING],
        \E_STRICT => ['Runtime Notice', LogLevel::WARNING],
        \E_WARNING => ['Warning', LogLevel::WARNING],
        \E_USER_WARNING => ['User Warning', LogLevel::WARNING],
        \E_COMPILE_WARNING => ['Compile Warning', LogLevel::WARNING],
        \E_CORE_WARNING => ['Core Warning', LogLevel::WARNING],
        \E_USER_ERROR => ['User Error', LogLevel::CRITICAL],
        \E_RECOVERABLE_ERROR => ['Catchable Fatal Error', LogLevel::CRITICAL],
        \E_COMPILE_ERROR => ['Compile Error', LogLevel::CRITICAL],
        \E_PARSE => ['Parse Error', LogLevel::CRITICAL],
        \E_ERROR => ['Error', LogLevel::CRITICAL],
        \E_CORE_ERROR => ['Core Error', LogLevel::CRITICAL],
    ];

    private static LoggerInterface $logger;

    private function __construct()
    {
    }

    /**
     */
    public static function register(LoggerInterface $logger): void
    {
        self::$logger = $logger;
        \set_error_handler(self::handleError(...));
        \set_exception_handler(self::handleException(...));
    }

    /**
     * @throws \ErrorException
     */
    public static function handleError(int $type, string $message, string $file, int $line): bool
    {
        $logMessage = \sprintf("%s: %s", self::ERRORS[$type][0], $message);
        $errorAsException = new \ErrorException($logMessage, 0, $type, $file, $line);
        $level = self::ERRORS[$type][1];

        if ($level === LogLevel::CRITICAL) {
            throw $errorAsException;
        }

        self::$logger->log($level, $logMessage, ['exception' => $errorAsException]);

        return true;
    }

    public static function handleException(\Throwable $exception): void
    {
        $message = match (true) {
            $exception instanceof \Error => 'Uncaught Error: ' . $exception->getMessage(),
            $exception instanceof \ErrorException => 'Uncaught: ' . $exception->getMessage(),
            default => 'Uncaught Exception: ' . $exception->getMessage(),
        };

        $level = match ($exception::class) {
            TlsHandshakeException::class, TooLargePayload::class, HttpException::class => LogLevel::NOTICE,
            default => LogLevel::CRITICAL,
        };

        self::$logger->log($level, $message, ['exception' => $exception]);
    }
}
