<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Test;

use GuzzleHttp\Cookie\CookieJar;

final class HttpProtocolTest extends ServerTestCase
{
    public function testRequestOkFromString(): void
    {
        // Act
        $response = self::$client->request('GET', 'http://127.0.0.1:9080/ok1');

        // Assert
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok-answer', (string) $response->getBody());
    }

    public function testRequestOkFromStream(): void
    {
        // Act
        $response = self::$client->request('GET', 'http://127.0.0.1:9080/ok2');

        // Assert
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok-answer from stream', (string) $response->getBody());
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
        $response = $this->requestJsonDecode('POST', 'http://127.0.0.1:9080/request', [
            'body' => '88lc5paair2xwnidlz9r6k0rpggkmbhb2oqr0go0cxc',
        ]);

        // Assert
        $this->assertSame('88lc5paair2xwnidlz9r6k0rpggkmbhb2oqr0go0cxc', $response['raw_request']);
    }

    public function testHeaders(): void
    {
        // Act
        $response = $this->requestJsonDecode('GET', 'http://127.0.0.1:9080/request', [
            'headers' => [
                'test-header-1' => '9hnwk8xuxzt8qdc4wcsrr26uqqsuz8',
            ],
        ]);

        // Assert
        $this->assertSame('9hnwk8xuxzt8qdc4wcsrr26uqqsuz8', $response['headers']['test-header-1'][0]);
    }

    public function testGetParameters(): void
    {
        // Act
        $response = $this->requestJsonDecode('GET', 'http://127.0.0.1:9080/request', [
            'query' => [
                'test-query-1' => '3kqz7kx610uewmcwyg44z',
            ],
        ]);

        // Assert
        $this->assertSame('3kqz7kx610uewmcwyg44z', $response['query']['test-query-1']);
    }

    public function testGetWithNoParameters(): void
    {
        // Act
        $response = $this->requestJsonDecode('GET', 'http://127.0.0.1:9080/request');

        // Assert
        $this->assertSame([], $response['query']);
    }

    public function testCookies(): void
    {
        // Act
        $response = $this->requestJsonDecode('GET', 'http://127.0.0.1:9080/request', [
            'cookies' => CookieJar::fromArray(domain: '127.0.0.1', cookies: [
                'test-cookie-1' => '94bt5trqjfqe6seo0',
                'test-cookie-2' => 'test1 test2',
            ]),
        ]);

        // Assert
        $this->assertSame('94bt5trqjfqe6seo0', $response['cookies']['test-cookie-1']);
        $this->assertSame('test1 test2', $response['cookies']['test-cookie-2']);
    }
}
