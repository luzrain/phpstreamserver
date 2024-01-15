<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Http\Psr7;

use Psr\Http\Message\UriInterface;

final class Uri implements UriInterface
{
    private const SCHEMES = [80 => 'http', 443 => 'https'];

    private string $scheme = '';
    private string $userInfo = '';
    private string $host = '';
    private int|null $port;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';

    private string|null $cache = null;

    public function __construct(string $uri)
    {
        if ($uri === '') {
            return;
        }

        if (false === $parts = \parse_url($uri)) {
            throw new \InvalidArgumentException(\sprintf('Unable to parse URI: "%s"', $uri));
        }

        $this->scheme = isset($parts['scheme']) ? $this->normalizeScheme($parts['scheme']) : '';
        $this->userInfo = isset($parts['user']) ? $this->normalizeUserInfo($parts['user'], $parts['pass'] ?? null) : '';
        $this->host = isset($parts['host']) ? $this->normalizeHost($parts['host']) : '';
        $this->port = isset($parts['port']) ? $this->normalizePort($parts['port']) : null;
        $this->path = isset($parts['path']) ? $this->normalizePath($parts['path']) : '';
        $this->query = isset($parts['query']) ? $this->normalizeQuery($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? $this->normalizeFragment($parts['fragment']) : '';
    }

    public function __toString(): string
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = '';

        if ($this->scheme !== '') {
            $this->cache .= $this->scheme . ':';
        }

        if (($authority = $this->getAuthority()) !== '') {
            $this->cache .= '//' . $authority;
        }

        if ($this->path !== '') {
            if ($authority === '') {
                // If the path is starting with more than one "/" and no authority is present,
                // the starting slashes MUST be reduced to one.
                $this->cache .= \str_starts_with($this->path, '/') ? '/' . \ltrim($this->path, '/') : $this->path;
            } else {
                // If the path is rootless and an authority is present, the path MUST be prefixed by "/".
                $this->cache .= \str_starts_with($this->path, '/') ? $this->path : '/' . $this->path;
            }
        }

        if ($this->query !== '') {
            $this->cache .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $this->cache .= '#' . $this->fragment;
        }

        return $this->cache;
    }

    public function __clone()
    {
        $this->cache = null;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if (($authority = $this->host) === '') {
            return '';
        }

        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->isNotStandardPort()) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int|null
    {
        return $this->isNotStandardPort() ? $this->port : null;
    }

    public function getPath(): string
    {
        if ($this->path === '' || $this->path === '/') {
            return $this->path;
        }

        // If the path is rootless and an authority is present, the path MUST be prefixed by "/".
        if (!str_starts_with($this->path, '/')) {
            return $this->host === '' ? $this->path : '/' . $this->path;
        }

        return '/' . \ltrim($this->path, '/');
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * @return static
     */
    public function withScheme(string $scheme): UriInterface
    {
        if ($this->scheme === $scheme = $this->normalizeScheme($scheme)) {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $scheme;
        $new->port = $new->normalizePort($new->port);

        return $new;
    }

    /**
     * @return static
     */
    public function withUserInfo(string $user, string|null $password = null): UriInterface
    {
        if ($this->userInfo === $userInfo = $this->normalizeUserInfo($user, $password)) {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $userInfo;

        return $new;
    }

    /**
     * @return static
     */
    public function withHost(string $host): UriInterface
    {
        if ($this->host === $host = $this->normalizeHost($host)) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;

        return $new;
    }

    /**
     * @return static
     */
    public function withPort(int|null $port): UriInterface
    {
        if ($this->port === $port = $this->normalizePort($port)) {
            return $this;
        }

        $new = clone $this;
        $new->port = $port;

        return $new;
    }

    /**
     * @return static
     */
    public function withPath(string $path): UriInterface
    {
        if ($this->path === $path = $this->normalizePath($path)) {
            return $this;
        }

        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    /**
     * @return static
     */
    public function withQuery(string $query): UriInterface
    {
        if ($this->query === $query = $this->normalizeQuery($query)) {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    /**
     * @return static
     */
    public function withFragment(string $fragment): UriInterface
    {
        if ($this->fragment === $fragment = $this->normalizeFragment($fragment)) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    private function normalizeScheme(string $scheme): string
    {
        return \strtolower($scheme);
    }

    private function normalizeUserInfo(string $user, string|null $pass = null): string
    {
        if ($user === '') {
            return '';
        }

        $pattern = '/(?:[^%a-zA-Z0-9_\-\.~\pL!\$&\'\(\)\*\+,;=]+|%(?![A-Fa-f0-9]{2}))/u';
        $userInfo = $this->encode($user, $pattern);

        if ($pass !== null) {
            $userInfo .= ':' . $this->encode($pass, $pattern);
        }

        return $userInfo;
    }

    private function normalizeHost(string $host): string
    {
        return \strtolower($host);
    }

    private function normalizePort(int|null $port): int|null
    {
        if ($port !== null && ($port < 0 || $port > 0xFFFF)) {
            throw new \InvalidArgumentException(\sprintf('Invalid port: %d. Must be between 0 and 65535', $port));
        }

        return $port;
    }

    private function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        return $this->encode($path, '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=%:@\/]++|%(?![A-Fa-f0-9]{2}))/');
    }

    private function normalizeQuery(string $query): string
    {
        if ($query === '' || $query === '?') {
            return '';
        }

        if ($query[0] === '?') {
            $query = \ltrim($query, '?');
        }

        return $this->encode($query, '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/');
    }

    private function normalizeFragment(string $fragment): string
    {
        if ($fragment === '' || $fragment === '#') {
            return '';
        }

        if (\str_starts_with($fragment, '#')) {
            $fragment = \ltrim($fragment, '#');
        }

        return $this->encode($fragment, '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/');
    }

    /**
     * Percent encodes all reserved characters in the provided string according to the provided pattern.
     * Characters that are already encoded as a percentage will not be re-encoded.
     *
     * @link https://tools.ietf.org/html/rfc3986
     */
    private function encode(string $string, string $pattern): string
    {
        return (string) \preg_replace_callback($pattern, static fn (array $matches) => \rawurlencode($matches[0]), $string);
    }

    private function isNotStandardPort(): bool
    {
        if ($this->port === null) {
            return false;
        }

        return !isset(self::SCHEMES[$this->port]) || $this->scheme !== self::SCHEMES[$this->port];
    }
}
