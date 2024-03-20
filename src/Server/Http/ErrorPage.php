<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Http;

use Luzrain\PHPStreamServer\Server;

final readonly class ErrorPage implements \Stringable
{
    private string $version;

    public function __construct(
        private int $code = 500,
        private string $title = '',
        private \Throwable|null $exception = null,
    ) {
        $this->version = Server::VERSION_STRING;
    }

    public function __toString()
    {
        return $this->exception !== null ? $this->getTemplateWithException($this->exception) : $this->getTemplateWithoutException();
    }

    private function getTemplateWithoutException(): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <title>{$this->code} {$this->title}</title>
                <style>
                    body {color: #333; font-family: Verdana, sans-serif; font-size: 0.9em; margin: 0; padding: 1rem;}
                    h1 {margin: 0;}
                    hr {margin: 1rem 0; border: 0; border-bottom: 1px solid #333;}
                </style>
            </head>
            <body>
            <h1>{$this->code} {$this->title}</h1>
            <hr>
            <div>{$this->version}</div>
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
                <title>{$this->code} {$this->title}</title>
                <style>
                    body {color: #333; font-family: Verdana, sans-serif; font-size: 0.9em; margin: 0; padding: 1rem;}
                    h1 {margin: 0;}
                    pre {margin: 1rem 0; white-space: pre-wrap;}
                    hr {margin: 1rem 0; border: 0; border-bottom: 1px solid #333;}
                </style>
            </head>
            <body>
            <h1>{$this->code} {$this->title}</h1>
            <div style="margin: 1rem 0;">
                <div>{$exceptionClass}: {$exception->getMessage()}</div>
                <div style="font-size:0.85em;">in <b>{$exception->getFile()}</b> on line <b>{$exception->getLine()}</b></div>
            </div>
            <pre>Stack trace:\n{$exception->getTraceAsString()}</pre>
            <hr>
            <div>{$this->version}</div>
            </body>
            </html>
            HTML;
    }
}
