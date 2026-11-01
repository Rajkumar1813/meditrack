<?php

declare(strict_types=1);

namespace MediTrack\Core;

class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $body;
    private array $headers;
    private array $cookies;
    private array $server;
    private array $files = [];
    private array $attributes = [];
    private ?string $rawBody = null;

    public function __construct(
        string $method,
        string $uri,
        array $headers = [],
        array $query = [],
        array $body = [],
        array $cookies = [],
        array $server = [],
        array $files = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $this->headers = array_change_key_case($headers, CASE_LOWER);

        if (empty($query) && ($queryString = parse_url($uri, PHP_URL_QUERY))) {
            parse_str($queryString, $parsedQuery);
            $query = $parsedQuery;
        }

        $this->query = $query;
        $this->body = $body;
        $this->cookies = $cookies;
        $this->server = $server;
        $this->files = $files;
    }

    public static function create(
        string $method,
        string $uri,
        array $headers = [],
        array $query = [],
        array $body = [],
        array $cookies = [],
        array $server = [],
        array $files = []
    ): self {
        return new self($method, $uri, $headers, $query, $body, $cookies, $server, $files);
    }

    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Extract headers
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = $value;
            }
        }

        $query = $_GET ?? [];
        $body = $_POST ?? [];
        $rawInput = file_get_contents('php://input');

        $contentType = $headers['content-type'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            if (!empty($rawInput)) {
                $decoded = json_decode($rawInput, true);
                if (is_array($decoded)) {
                    $body = array_merge($body, $decoded);
                }
            }
        }

        $request = new self($method, $uri, $headers, $query, $body, $_COOKIE ?? [], $_SERVER, $_FILES ?? []);
        if ($rawInput !== false) {
            $request->setRawBody($rawInput);
        }
        return $request;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function body(): array
    {
        return $this->body;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        if (isset($this->headers[$normalized])) {
            return $this->headers[$normalized];
        }

        // Support HTTP_ prefix fallback
        $httpKey = 'http_' . str_replace('-', '_', $normalized);
        if (isset($this->headers[$httpKey])) {
            return $this->headers[$httpKey];
        }

        $serverKey = strtoupper($httpKey);
        if (isset($this->server[$serverKey])) {
            return $this->server[$serverKey];
        }

        return $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        return $this->cookies[$name] ?? $default;
    }

    public function files(?string $key = null): array
    {
        if ($key !== null) {
            return $this->files[$key] ?? [];
        }
        return $this->files;
    }

    public function withFiles(array $files): self
    {
        $clone = clone $this;
        $clone->files = $files;
        return $clone;
    }

    public function setRawBody(?string $rawBody): self
    {
        $this->rawBody = $rawBody;
        return $this;
    }

    public function withRawBody(string $rawBody): self
    {
        $clone = clone $this;
        $clone->rawBody = $rawBody;
        return $clone;
    }

    public function rawBody(): string
    {
        if ($this->rawBody !== null) {
            return $this->rawBody;
        }
        $input = file_get_contents('php://input');
        return $input !== false ? $input : '';
    }

    public function ip(): string
    {
        if (!empty($this->server['HTTP_CF_CONNECTING_IP'])) {
            return $this->server['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $this->server['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth && preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->getAttribute($key, $default);
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('accept', '');
        return str_contains($accept, 'application/json');
    }
}

