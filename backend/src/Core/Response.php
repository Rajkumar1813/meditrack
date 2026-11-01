<?php

declare(strict_types=1);

namespace MediTrack\Core;

class Response
{
    protected string $content;
    protected int $statusCode;
    protected array $headers;
    protected array $cookies = [];

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function cookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Strict'
    ): self {
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];
        return $this;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $normalized) {
                return $v;
            }
        }
        return $default;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getBody(): string
    {
        return $this->content;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
            foreach ($this->cookies as $c) {
                setcookie($c['name'], $c['value'], [
                    'expires' => $c['expires'],
                    'path' => $c['path'],
                    'domain' => $c['domain'],
                    'secure' => $c['secure'],
                    'httponly' => $c['httponly'],
                    'samesite' => $c['samesite'],
                ]);
            }
        }

        echo $this->content;
    }
}

