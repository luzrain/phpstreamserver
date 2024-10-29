<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal;

use Luzrain\PHPStreamServer\Server;

final readonly class ErrorPage
{
    public string $server;

    public function __construct(
        public int $status = 500,
        public string $reason = '',
        public \Throwable|null $exception = null,
    ) {
        $this->server = Server::VERSION_STRING;
    }

    public function toHtml(): string
    {
        return $this->exception !== null ? $this->getTemplateWithException($this->exception) : $this->getTemplateWithoutException();
    }

    private function getTemplateWithoutException(): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <title>{$this->status} {$this->reason}</title>
                <style>
                    body {color: #333; font-family: Verdana, sans-serif; font-size: 0.9em; margin: 0; padding: 1rem;}
                    h1 {margin: 0;}
                    hr {margin: 1rem 0; border: 0; border-bottom: 1px solid #333;}
                </style>
            </head>
            <body>
            <h1>{$this->status} {$this->reason}</h1>
            <hr>
            <div>{$this->server}</div>
            </body>
            </html>
            HTML;
    }

    private function getTemplateWithException(\Throwable $exception): string
    {
        $exceptionClass = $exception::class;
        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <title>{$this->status} {$this->reason}</title>
                <style>
                    body {color: #333; font-family: Verdana, sans-serif; font-size: 0.9em; margin: 0; padding: 1rem;}
                    h1 {margin: 0;}
                    pre {margin: 1rem 0; white-space: pre-wrap;}
                    hr {margin: 1rem 0; border: 0; border-bottom: 1px solid #333;}
                </style>
            </head>
            <body>
            <h1>{$this->status} {$this->reason}</h1>
            <div style="margin: 1rem 0;">
                <div>{$exceptionClass}: {$exception->getMessage()}</div>
                <div style="font-size:0.85em;">in <b>{$exception->getFile()}</b> on line <b>{$exception->getLine()}</b></div>
            </div>
            <pre>Stack trace:\n{$exception->getTraceAsString()}</pre>
            <hr>
            <div>{$this->server}</div>
            </body>
            </html>
            HTML;
    }
}
