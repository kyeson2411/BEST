<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

/**
 * Minimal JSON-over-HTTPS client for AI providers (curl). Supports server-sent-event
 * streaming with incremental parsing, cancellation, timeouts and retry-after capture.
 * API keys only ever travel in request headers built on the server.
 */
final class Http
{
    /**
     * @param list<string> $headers
     * @param callable(string $event, array $data):void|null $onEvent SSE handler; null for a plain JSON response
     * @param callable():bool|null $shouldStop
     * @return array{status:int,body:string,retry_after:?int,cancelled:bool,error:?string,errno:int}
     */
    public static function postJson(string $url, array $headers, array $body, int $timeout, ?callable $onEvent = null, ?callable $shouldStop = null): array
    {
        if (!function_exists('curl_init')) {
            throw new AiException('not_configured', t('The PHP curl extension is required to reach the AI service.'));
        }
        $ch = curl_init($url);
        $raw = '';
        $buffer = '';
        $cancelled = false;
        $retryAfter = null;
        $status = 0;

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: ' . ($onEvent ? 'text/event-stream' : 'application/json')], $headers),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$retryAfter): int {
                if (stripos($line, 'retry-after:') === 0) {
                    $value = trim(substr($line, 12));
                    $retryAfter = ctype_digit($value) ? (int) $value : null;
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$raw, &$buffer, &$cancelled, &$status, $onEvent, $shouldStop): int {
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                if ($onEvent === null || $status !== 200) {
                    $raw .= $chunk;
                    return strlen($chunk);
                }
                if ($shouldStop !== null && $shouldStop()) {
                    $cancelled = true;
                    return 0; // aborts the transfer
                }
                $buffer .= str_replace("\r\n", "\n", $chunk);
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $block = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);
                    $event = 'message';
                    $data = '';
                    foreach (explode("\n", $block) as $line) {
                        if (str_starts_with($line, 'event:')) {
                            $event = trim(substr($line, 6));
                        } elseif (str_starts_with($line, 'data:')) {
                            $data .= ltrim(substr($line, 5));
                        }
                    }
                    if ($data === '') {
                        continue;
                    }
                    if ($data === '[DONE]') {
                        $onEvent('done', []);
                        continue;
                    }
                    $decoded = json_decode($data, true);
                    if (is_array($decoded)) {
                        $onEvent($event, $decoded);
                    }
                }
                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $ok === false ? curl_error($ch) : null;
        if ($status === 0) {
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        }
        curl_close($ch);

        if ($cancelled) {
            return ['status' => $status, 'body' => $raw, 'retry_after' => $retryAfter, 'cancelled' => true, 'error' => null, 'errno' => 0];
        }
        if ($ok === false && $status === 0) {
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                // Not tried again: the service already had the whole timeout, and three waits would outlast the
                // request itself (the server stops it after 240 seconds) and leave the student with nothing.
                throw new AiException('timeout', t('The AI service is taking too long to answer. Try again in a few minutes. Everything else in B.E.S.T. Learning and Interactive Study System works as usual.'), false);
            }
            throw new AiException('network', t('B.E.S.T. Learning and Interactive Study System can\'t reach the AI service right now. Try again in a few minutes. Everything else in B.E.S.T. Learning and Interactive Study System works as usual.'), true);
        }
        return ['status' => $status, 'body' => $raw, 'retry_after' => $retryAfter, 'cancelled' => false, 'error' => $error, 'errno' => $errno];
    }

    /** Provider error message from a JSON error body (never shown raw outside local mode). */
    public static function errorMessage(string $body): string
    {
        $json = json_decode($body, true);
        if (is_array($json)) {
            return (string) ($json['error']['message'] ?? $json['message'] ?? '');
        }
        return mb_substr(trim(strip_tags($body)), 0, 200);
    }
}
