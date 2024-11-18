<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

final class TextProtocolTest extends ServerTestCase
{
    public function testTcpConnection(): void
    {
        // Arrange
        $fp = $this->streamSocketClient('tcp://127.0.0.1:9082');

        // Act
        \fwrite($fp, "test1234567890-tcp\n");
        $response = $this->fread($fp);

        // Assert
        $this->assertSame("echo:test1234567890-tcp\n", $response);
    }

    public function testUdpConnection(): void
    {
        // Arrange
        $fp = $this->streamSocketClient('udp://127.0.0.1:9083');

        // Act
        \fwrite($fp, "test1234567890-udp\n");
        $response = $this->fread($fp);

        // Assert
        $this->assertSame("echo:test1234567890-udp\n", $response);
    }
}
