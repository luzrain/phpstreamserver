<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

final class HttpChunkedPostRequestTest extends ServerTestCase
{
    public function testRawPostRequest(): void
    {
        // Act
        $response = $this->requestJsonDecode('POST', 'http://127.0.0.1:9080/request', [
            'headers' => [
                'Transfer-Encoding' => 'chunked',
            ],
            'body' => 'ChunkedRequestTest1111',
        ]);

        // Assert
        $this->assertSame('ChunkedRequestTest1111', $response['raw_request']);
    }

    public function testPostRequest(): void
    {
        // Act
        $response = $this->requestJsonDecode('POST', 'http://127.0.0.1:9080/request', [
            'headers' => [
                'Transfer-Encoding' => 'chunked',
            ],
            'form_params' => [
                'test-1' => '88lc5paalpm0',
                'test-2' => 'ee1jnre222',
            ],
        ]);

        // Assert
        $this->assertSame('88lc5paalpm0', $response['request']['test-1']);
        $this->assertSame('ee1jnre222', $response['request']['test-2']);
    }
}
