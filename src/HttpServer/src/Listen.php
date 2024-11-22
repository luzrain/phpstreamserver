<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer;

final readonly class Listen
{
    public string $host;
    public int $port;

    public function __construct(
        string $listen,
        public bool $tls = false,
        public string|null $tlsCertificate = null,
        public string|null $tlsCertificateKey = null,
    ) {
        $p = \parse_url('tcp://' . $listen);
        $this->host = $p['host'] ?? $p;
        $this->port = $p['port'] ?? ($tls ? 443 : 80);

        if (\str_contains($listen, '://')) {
            throw new \InvalidArgumentException('Listen should not contain schema');
        }

        if ($this->port < 0 || $this->port > 65535) {
            throw new \InvalidArgumentException('Port number must be an integer between 0 and 65535; got ' . $this->port);
        }

        if ($tls && $tlsCertificate === null) {
            throw new \InvalidArgumentException('Certificate file must be provided');
        }
    }

    public function getAddress(): string
    {
        return ($this->tls ? 'https://' : 'http://') . $this->host .
            (($this->tls && $this->port === 443) || (!$this->tls && $this->port === 80) ? '' : ':' . $this->port);
    }
}
