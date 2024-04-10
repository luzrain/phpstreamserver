<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Test;

final class TooLargePayloadTest extends ServerTestCase
{
    public function testHeadersTooLarge(): void
    {
        $this->markTestSkipped();

        // Act
        $response = self::$client->request('POST', 'http://127.0.0.1:9086/request', [
            'headers' => [
                'Test-Header' => str_repeat('0', 10000),
            ],
            'body' => '111222',
        ]);

        dump((string) $response->getBody());

        // Assert
        $this->assertSame(413, $response->getStatusCode());
    }
}
