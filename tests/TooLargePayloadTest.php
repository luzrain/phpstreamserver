<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

final class TooLargePayloadTest extends ServerTestCase
{
    public function testHeadersAndBodyIsSmallEnought(): void
    {
        // Act
        $response = self::$client->request('POST', 'http://127.0.0.1:9086/request', [
            'headers' => [
                'Test-Header' => \str_repeat('0', 20000),
            ],
            'body' => \str_repeat('0', 102000),
        ]);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHeadersTooLarge1(): void
    {
        // Act
        $response = self::$client->request('POST', 'http://127.0.0.1:9086/request', [
            'headers' => [
                'Test-Header' => \str_repeat('0', 40000),
            ],
            'body' => '111222',
        ]);

        // Assert
        $this->assertSame(431, $response->getStatusCode());
    }

    public function testHeadersTooLarge2(): void
    {
        // Act
        $response = self::$client->request('POST', 'http://127.0.0.1:9086/request', [
            'headers' => [
                'Test-Header' => \str_repeat('0', 200000),
            ],
            'body' => '111222',
        ]);

        // Assert
        $this->assertSame(431, $response->getStatusCode());
    }

    public function testBodyTooLarge(): void
    {
        // Act
        $response = self::$client->request('POST', 'http://127.0.0.1:9086/request', [
            'body' => \str_repeat('0', 103000),
        ]);

        // Assert
        $this->assertSame(413, $response->getStatusCode());
    }
}
