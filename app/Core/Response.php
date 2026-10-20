<?php

declare(strict_types=1);

namespace Sibol\Core;

final class Response
{
    public int $status = 200;
    /** @var array<string,string> */
    public array $headers = [];
    public string $body = '';
    /** @var callable|null */
    public $streamer = null;
    public ?string $filePath = null;

    public static function html(string $html, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->body = $html;
        $r->headers['Content-Type'] = 'text/html; charset=utf-8';
        return $r;
    }

    /** @param mixed $data */
    public static function json($data, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->body = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $r->headers['Content-Type'] = 'application/json; charset=utf-8';
        $r->headers['Cache-Control'] = 'no-store';
        return $r;
    }

    /** $to is an app path ("/student") or an absolute URL on this site. */
    public static function redirect(string $to, int $status = 303): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Location'] = preg_match('#^https?://#i', $to) ? $to : url($to);
        return $r;
    }

    public static function file(string $absolutePath, string $downloadName, string $mime, bool $inline = false): self
    {
        $r = new self();
        $r->filePath = $absolutePath;
        $safeName = str_replace(['"', '\\', "\r", "\n"], '', $downloadName);
        $ascii = (string) preg_replace('/[^\x20-\x7E]/', '_', $safeName);
        $r->headers['Content-Type'] = $mime;
        $r->headers['Content-Disposition'] = sprintf(
            '%s; filename="%s"; filename*=UTF-8\'\'%s',
            $inline ? 'inline' : 'attachment',
            $ascii,
            rawurlencode($safeName)
        );
        $r->headers['Content-Length'] = (string) filesize($absolutePath);
        $r->headers['Cache-Control'] = 'private, no-store';
        // A stored file must never be interpreted as a page on our origin.
        $r->headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        return $r;
    }

    /** Streams output produced by $fn (used for AI responses). */
    public static function stream(callable $fn, string $contentType = 'application/x-ndjson; charset=utf-8'): self
    {
        $r = new self();
        $r->streamer = $fn;
        $r->headers['Content-Type'] = $contentType;
        $r->headers['Cache-Control'] = 'no-store';
        $r->headers['X-Accel-Buffering'] = 'no';
        return $r;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        if ($this->streamer !== null) {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ($this->streamer)();
            return;
        }
        if ($this->filePath !== null) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            readfile($this->filePath);
            return;
        }
        echo $this->body;
    }
}
