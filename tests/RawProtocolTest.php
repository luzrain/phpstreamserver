<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Test;

final class RawProtocolTest extends ServerTestCase
{
    public function testTcpConnection(): void
    {
        // Arrange
        $fp = $this->streamSocketClient('tcp://127.0.0.1:9084');

        // Act
        \fwrite($fp, "test1234567890-tcp");
        $response = $this->fread($fp);

        // Assert
        $this->assertSame("echo:test1234567890-tcp", $response);
    }

    public function testUdpConnection(): void
    {
        // Arrange
        $fp = $this->streamSocketClient('udp://127.0.0.1:9085');

        // Act
        \fwrite($fp, "test1234567890-udp");
        $response = $this->fread($fp);

        // Assert
        $this->assertSame("echo:test1234567890-udp", $response);
    }
}
