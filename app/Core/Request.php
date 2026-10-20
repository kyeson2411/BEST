<?php

declare(strict_types=1);

namespace Sibol\Core;

final class Request
{
    public string $method;
    public string $path;
    /** @var array<string,mixed> */
    public array $query;
    /** @var array<string,mixed> */
    public array $post;
    /** @var array<string,mixed> */
    public array $files;
    /** @var array<string,mixed> */
    public array $server;
    /** @var array<string,int|string> */
    public array $params = [];
    private ?array $jsonBody = null;

    public function __construct(string $method, string $path, array $query = [], array $post = [], array $files = [], array $server = [])
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->server = $server;
    }

    public static function capture(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
        $base = base_path_url();
        if ($base !== '' && strncasecmp($path, $base, strlen($base)) === 0) {
            $path = substr($path, strlen($base));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }
        $path = '/' . trim($path, '/');
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        return new self($method, $path, $_GET, $_POST, $_FILES, $_SERVER);
    }

    /** @return mixed */
    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        $json = $this->json();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }
        return $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        if (is_array($value) || $value === null) {
            return $default;
        }
        // Normalise line endings and strip invisible control characters (keep tab/newline).
        $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        return trim($value);
    }

    public function int(string $key, ?int $default = null): ?int
    {
        $value = $this->input($key);
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d{1,10}$/', trim($value))) {
            return (int) trim($value);
        }
        return $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key);
        return in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true);
    }

    /** @return array<mixed> */
    public function arr(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? $value : [];
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->json(), $this->post);
    }

    /**
     * Normalised single uploaded file, or null when none was chosen.
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            return null;
        }
        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return [
            'name' => (string) $file['name'],
            'type' => (string) $file['type'],
            'tmp_name' => (string) $file['tmp_name'],
            'error' => (int) $file['error'],
            'size' => (int) $file['size'],
        ];
    }

    /** @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}> */
    public function fileList(string $key): array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || !isset($file['error'])) {
            return [];
        }
        if (!is_array($file['error'])) {
            $single = $this->file($key);
            return $single ? [$single] : [];
        }
        $out = [];
        foreach ($file['error'] as $i => $error) {
            if ((int) $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => (string) $file['name'][$i],
                'type' => (string) $file['type'][$i],
                'tmp_name' => (string) $file['tmp_name'][$i],
                'error' => (int) $error,
                'size' => (int) $file['size'][$i],
            ];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }
        $this->jsonBody = [];
        $type = (string) ($this->server['CONTENT_TYPE'] ?? '');
        if (stripos($type, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw === false ? '' : $raw, true);
            if (is_array($decoded)) {
                $this->jsonBody = $decoded;
            }
        }
        return $this->jsonBody;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($this->server['HTTP_ACCEPT'] ?? '');
        return str_contains($accept, 'application/json')
            || strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;
        return $value === null ? null : (string) $value;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isLocalhost(): bool
    {
        return in_array($this->ip(), ['127.0.0.1', '::1'], true);
    }

    /** @return int|string|null */
    public function param(string $name)
    {
        return $this->params[$name] ?? null;
    }

    /** Current URL path + query, used for "return to" links. */
    public function fullPath(): string
    {
        $qs = $this->query ? '?' . http_build_query($this->query) : '';
        return $this->path . $qs;
    }
}
