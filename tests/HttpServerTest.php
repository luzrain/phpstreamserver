<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Test;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\MultipartStream;
use PHPUnit\Framework\TestCase;

final class HttpServerTest extends TestCase
{
    private static Client $client;

    public static function setUpBeforeClass(): void
    {
        self::$client = new Client(['http_errors' => false, 'verify' => false]);
    }

    private function request(string $method, string $uri, array $options = []): array
    {
        return \json_decode((string) self::$client->request($method, $uri, $options)->getBody(), true);
    }

    public function testRequestOk(): void
    {
        // Act
        $response = self::$client->request('GET', 'http://127.0.0.1:9080/ok');

        // Assert
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok-answer', (string) $response->getBody());
    }

    public function testRequestNotFound(): void
    {
        // Act
        $response = self::$client->request('GET', 'http://127.0.0.1:9080/test999999999');

        // Assert
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testTLSRequestOk(): void
    {
        // Act
        $response = self::$client->request('GET', 'https://127.0.0.1:9081/');

        // Assert
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok-answer-tls', (string) $response->getBody());
    }

    public function testHttpRequestOverTLSReturnsBadRequest(): void
    {
        // Act
        $response = self::$client->request('GET', 'http://127.0.0.1:9081/');

        // Assert
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testRawRequest(): void
    {
        // Act
        $response = $this->request('POST', 'http://127.0.0.1:9080/request', [
            'body' => '88lc5paair2xwnidlz9r6k0rpggkmbhb2oqr0go0cxc',
        ]);

        // Assert
        $this->assertSame('88lc5paair2xwnidlz9r6k0rpggkmbhb2oqr0go0cxc', $response['raw_request']);
    }

    public function testHeaders(): void
    {
        // Act
        $response = $this->request('GET', 'http://127.0.0.1:9080/request', [
            'headers' => [
                'test-header-1' => '9hnwk8xuxzt8qdc4wcsrr26uqqsuz8',
            ],
        ]);

        // Assert
        $this->assertSame('9hnwk8xuxzt8qdc4wcsrr26uqqsuz8', $response['headers']['test-header-1'][0] ?? null);
    }

    public function testGet(): void
    {
        // Act
        $response = $this->request('GET', 'http://127.0.0.1:9080/request', [
            'query' => [
                'test-query-1' => '3kqz7kx610uewmcwyg44z',
            ],
        ]);

        // Assert
        $this->assertSame('3kqz7kx610uewmcwyg44z', $response['query']['test-query-1'] ?? null);
    }

    public function testPost(): void
    {
        $this->markTestIncomplete('Not implemented yet');

        // Act
        $response = $this->request('GET', 'http://127.0.0.1:9080/request', [
            'form_params' => [
                'test-post-1' => '88lc5paair2x',
            ],
        ]);

        // Assert
        $this->assertSame('88lc5paair2x', $response['request']['test-post-1'] ?? null);
    }

    public function testCookies(): void
    {
        // Act
        $response = $this->request('GET', 'http://127.0.0.1:9080/request', [
            'cookies' => CookieJar::fromArray(domain: '127.0.0.1', cookies: [
                'test-cookie-1' => '94bt5trqjfqe6seo0',
            ]),
        ]);

        // Assert
        $this->assertSame('94bt5trqjfqe6seo0', $response['cookies']['test-cookie-1'] ?? null);
    }

    public function testFiles(): void
    {
        $this->markTestIncomplete('Not implemented yet');

        // Act
        $response = $this->request('POST', 'http://127.0.0.1:9080/request', [
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=OEZCxUAIiopEcaUw',
            ],
            'body' => new MultipartStream(boundary: 'OEZCxUAIiopEcaUw11', elements: [
                [
                    'name' => 'test-file-1',
                    'filename' => 'test1.txt',
                    'contents' => 'b8owxkeuhjeq3kqz7kx610uewmcwygap',
                ],
            ]),
        ]);

        // Assert
        $this->assertSame('test-file-1', $response['files'][0]['name'] ?? null);
        $this->assertSame('test1.txt', $response['files'][0]['filename'] ?? null);
        $this->assertSame('txt', $response['files'][0]['extension'] ?? null);
        $this->assertSame('b8owxkeuhjeq3kqz7kx610uewmcwygap', $response['files'][0]['content'] ?? null);
        $this->assertSame(32, $response['files'][0]['size'] ?? null);
    }
}
