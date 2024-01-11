<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Test;

use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Stream;

final class HttpPostRequestTest extends ServerTestCase
{
    public function testRawPostRequest(): void
    {
        // Act
        $response = $this->requestJsonDecode('POST', 'http://127.0.0.1:9080/request', [
            'body' => 'tt1=1&tt2=2',
        ]);

        // Assert
        $this->assertSame([], $response['request']);
        $this->assertSame('tt1=1&tt2=2', $response['raw_request']);
    }

    public function testPostFormRequest(): void
    {
        // Act
        $response = $this->requestJsonDecode('POST', 'http://127.0.0.1:9080/request', [
            'form_params' => [
                'test-1' => '88lc5paair2x',
                'test-2' => 'ee1jnre wie8',
            ],
        ]);

        // Assert
        $this->assertSame('88lc5paair2x', $response['request']['test-1']);
        $this->assertSame('ee1jnre wie8', $response['request']['test-2']);
    }

    public function testJsonRequest(): void
    {
        // Act
        $response = $this->requestJsonDecode('POST', 'http://127.0.0.1:9080/request', [
            'json' => [
                'test-1' => 'c865admkpp39',
                'test-2' => 'ezb99e1usxkv',
                'test-3' => null,
                'test-4' => ['t1' => 'test1', 't2' => 123],
            ],
        ]);

        // Assert
        $this->assertSame('c865admkpp39', $response['request']['test-1']);
        $this->assertSame('ezb99e1usxkv', $response['request']['test-2']);
        $this->assertSame(null, $response['request']['test-3']);
        $this->assertSame(['t1' => 'test1', 't2' => 123], $response['request']['test-4']);
    }

    public function testMultipartRequest(): void
    {
        // Arrange
        $bigFileResource = \fopen('php://temp', 'rw');
        for ($i = 0; $i < 1000; $i++) {
            \fwrite($bigFileResource, \str_repeat('0', 100000));
        }
        \rewind($bigFileResource);

        // Act
        $response = $this->requestJsonDecode('POST', 'http://127.0.0.1:9080/request', [
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=OEZCxUAIiopEcaUw',
            ],
            'body' => new MultipartStream(boundary: 'OEZCxUAIiopEcaUw', elements: [
                [
                    'name' => 'test-1',
                    'contents' => 'test-1-data',
                ],
                [
                    'name' => 'test-2',
                    'contents' => 'test-2-data',
                ],
                [
                    'name' => 'file_one[]',
                    'filename' => 'test1.txt',
                    'contents' => "b8owxkeuhjeq3kqz7kx610uewmcwygap content\nooooommm mmezxssdfdsfd123123123123",
                ],
                [
                    'name' => 'file_one[]',
                    'filename' => 'test2.txt',
                    'contents' => '11111111111111111111111122222222222233333333339',
                ],
                [
                    'name' => 'file_three',
                    'filename' => 'test3.txt',
                    'contents' => 'test content for file three',
                ],
                [
                    'name' => 'image',
                    'filename' => 'dot.png',
                    'contents' => \base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true),
                ],
                [
                    'name' => 'big_file',
                    'filename' => 't.bin',
                    'contents' => new Stream($bigFileResource),
                ],
            ]),
        ]);

        // Assert request
        $this->assertCount(2, $response['request']);
        $this->assertSame('test-1-data', $response['request']['test-1']);
        $this->assertSame('test-2-data', $response['request']['test-2']);

        // Assert files
        $file = $response['files']['file_one'][0];
        $this->assertSame('test1.txt', $file['client_filename']);
        $this->assertSame('text/plain', $file['client_media_type']);
        $this->assertSame(75, $file['size']);
        $this->assertSame('781eaba2e9a92ddf42748bd8f56a9990459ea413', $file['sha1']);

        $file = $response['files']['file_one'][1];
        $this->assertSame('test2.txt', $file['client_filename']);
        $this->assertSame('text/plain', $file['client_media_type']);
        $this->assertSame(47, $file['size']);
        $this->assertSame('f69850b7b6dddf24c14581956f5b6aa3ae9cd54e', $file['sha1']);

        $file = $response['files']['file_three'];
        $this->assertSame('test3.txt', $file['client_filename']);
        $this->assertSame('text/plain', $file['client_media_type']);
        $this->assertSame(27, $file['size']);
        $this->assertSame('4c129254b51981cba03e4c8aac82bb329880971a', $file['sha1']);

        $file = $response['files']['image'];
        $this->assertSame('dot.png', $file['client_filename']);
        $this->assertSame('image/png', $file['client_media_type']);
        $this->assertSame(70, $file['size']);
        $this->assertSame('4a5eb7171b58e08a6881721e3b43d5a44419a2be', $file['sha1']);

        $file = $response['files']['big_file'];
        $this->assertSame('t.bin', $file['client_filename']);
        $this->assertSame('application/octet-stream', $file['client_media_type']);
        $this->assertSame(100000000, $file['size']);
        $this->assertSame('359c2fd12051ccb95e8828bfffdc8d07c9b97a13', $file['sha1']);
    }
}
