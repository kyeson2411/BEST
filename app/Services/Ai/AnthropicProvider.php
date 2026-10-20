<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

use Sibol\Core\Config;
use Sibol\Core\Logger;

/**
 * Claude via the Anthropic Messages API (raw HTTPS).
 *
 * The official anthropic-ai/sdk PHP package requires PHP 8.1+, and XAMPP here ships
 * PHP 8.0, so this adapter speaks the documented HTTP API directly. On PHP 8.2+ it can
 * be swapped for the SDK behind the same AiProvider interface.
 *
 * Notes for current models (per Anthropic's API reference):
 *  - claude-opus-5-5 is the default. Thinking is always on (adaptive); depth is set with
 *    output_config.effort (default "medium" on this model, so it is sent explicitly).
 *  - Sampling parameters such as temperature are rejected by current models, so none are sent.
 *  - Safety classifiers can decline with HTTP 200 + stop_reason "refusal"; we opt into the
 *    server-side fallback ("fallbacks": "default") and discard any partial text on refusal.
 */
final class AnthropicProvider implements AiProvider
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const VERSION = '2023-06-01';
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';
    /** Models that accept output_config.effort. */
    private const EFFORT_MODELS = ['claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5', 'claude-sonnet-5', 'claude-haiku-5-5', 'claude-fable-5-1', 'claude-fable-5', 'claude-opus-4-8', 'claude-opus-4-7', 'claude-opus-4-6', 'claude-sonnet-4-6'];
    /** Models where the server-side "default" fallback applies. */
    private const FALLBACK_MODELS = ['claude-opus-5-5', 'claude-opus-5', 'claude-fable-5-1', 'claude-sonnet-5-5'];
    private const DEFAULT_MODEL = 'claude-opus-5-5';

    private string $apiKey;
    private string $model;
    private int $timeout;
    private static bool $fallbacksDisabled = false;

    public function __construct(string $apiKey, ?string $model = null, int $timeout = 90)
    {
        $this->apiKey = $apiKey;
        $this->model = $model ?: self::DEFAULT_MODEL;
        $this->timeout = max(20, $timeout);
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function isDemo(): bool
    {
        return false;
    }

    public function complete(AiRequest $request, ?callable $onText = null, ?callable $shouldStop = null): AiResult
    {
        $attempt = 0;
        $emitted = false;
        $wrappedOnText = $onText === null ? null : static function (string $text) use ($onText, &$emitted): void {
            $emitted = true;
            $onText($text);
        };
        while (true) {
            $attempt++;
            try {
                return $this->once($request, $wrappedOnText, $shouldStop);
            } catch (AiException $e) {
                if ($e->errorCode === 'bad_request' && !self::$fallbacksDisabled && $this->usesFallbacks() && stripos($e->getMessage() . ' ' . $e->status, 'beta') !== false) {
                    // The fallback beta is not enabled for this organisation: continue without it.
                    self::$fallbacksDisabled = true;
                    Logger::warning('Anthropic fallback beta rejected; continuing without server-side fallback');
                    continue;
                }
                if (!$e->retryable || $emitted || $attempt >= 3) {
                    throw $e;
                }
                $wait = $e->retryAfter !== null ? min(20, max(1, $e->retryAfter)) : ($attempt === 1 ? 1 : 3);
                sleep($wait);
            }
        }
    }

    private function usesFallbacks(): bool
    {
        return in_array($this->model, self::FALLBACK_MODELS, true) && Config::get('ai.fallbacks', 'default') !== 'off';
    }

    private function once(AiRequest $request, ?callable $onText, ?callable $shouldStop): AiResult
    {
        $started = microtime(true);
        $body = [
            'model' => $this->model,
            'max_tokens' => $request->maxTokens,
            'system' => $request->system,
            'messages' => $this->messages($request->messages),
        ];
        if (in_array($this->model, self::EFFORT_MODELS, true)) {
            $body['output_config'] = ['effort' => $request->effort];
        }
        $headers = [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: ' . self::VERSION,
        ];
        if ($this->usesFallbacks() && !self::$fallbacksDisabled) {
            $body['fallbacks'] = 'default';
            $headers[] = 'anthropic-beta: ' . self::FALLBACK_BETA;
        }

        $result = new AiResult();
        $result->model = $this->model;

        if ($onText === null) {
            $response = Http::postJson(self::ENDPOINT, $headers, $body, $this->timeout);
            $this->assertOk($response);
            $json = json_decode($response['body'], true);
            if (!is_array($json)) {
                throw new AiException('invalid_output', t('The AI service sent an unreadable response. Try again.'), true);
            }
            foreach ((array) ($json['content'] ?? []) as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $result->text .= (string) $block['text'];
                }
            }
            $result->model = (string) ($json['model'] ?? $this->model);
            $result->stopReason = $json['stop_reason'] ?? null;
            $this->applyUsage($result, (array) ($json['usage'] ?? []));
        } else {
            $blockTypes = [];
            $streamError = null;
            $response = Http::postJson(self::ENDPOINT, $headers, $body + ['stream' => true], $this->timeout,
                function (string $event, array $data) use ($result, $onText, &$blockTypes, &$streamError): void {
                    switch ($data['type'] ?? $event) {
                        case 'message_start':
                            $result->model = (string) ($data['message']['model'] ?? $result->model);
                            $this->applyUsage($result, (array) ($data['message']['usage'] ?? []));
                            break;
                        case 'content_block_start':
                            $blockTypes[(int) ($data['index'] ?? 0)] = (string) ($data['content_block']['type'] ?? '');
                            break;
                        case 'content_block_delta':
                            $index = (int) ($data['index'] ?? 0);
                            if (($data['delta']['type'] ?? '') === 'text_delta' && ($blockTypes[$index] ?? 'text') === 'text') {
                                $text = (string) ($data['delta']['text'] ?? '');
                                $result->text .= $text;
                                $onText($text);
                            }
                            break;
                        case 'message_delta':
                            $result->stopReason = $data['delta']['stop_reason'] ?? $result->stopReason;
                            $this->applyUsage($result, (array) ($data['usage'] ?? []));
                            break;
                        case 'error':
                            $streamError = (array) ($data['error'] ?? []);
                            break;
                    }
                }, $shouldStop);
            if ($response['cancelled']) {
                $result->cancelled = true;
            } else {
                $this->assertOk($response);
                if ($streamError !== null) {
                    $type = (string) ($streamError['type'] ?? 'api_error');
                    if ($result->text === '') {
                        throw AiException::fromStatus($type === 'overloaded_error' ? 529 : ($type === 'rate_limit_error' ? 429 : 500), (string) ($streamError['message'] ?? ''));
                    }
                    $result->errorCode = $type === 'overloaded_error' ? 'overloaded' : 'server_error';
                }
            }
        }

        $result->refused = $result->stopReason === 'refusal';
        $result->truncated = $result->stopReason === 'max_tokens';
        if ($result->refused) {
            $result->text = '';
        }
        $result->latencyMs = (int) round((microtime(true) - $started) * 1000);
        return $result;
    }

    /** @param list<array{role:string,content:string}> $messages */
    private function messages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            $role = $m['role'] === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) $m['content']);
            if ($content === '') {
                continue;
            }
            // Merge consecutive same-role turns to keep the history well formed.
            if ($out && $out[count($out) - 1]['role'] === $role) {
                $out[count($out) - 1]['content'] .= "\n\n" . $content;
                continue;
            }
            $out[] = ['role' => $role, 'content' => $content];
        }
        if (!$out || $out[0]['role'] !== 'user') {
            array_unshift($out, ['role' => 'user', 'content' => '(Conversation start)']);
        }
        return $out;
    }

    /** Usage may arrive in parts; iterations (per fallback attempt) are the billing source of truth. */
    private function applyUsage(AiResult $result, array $usage): void
    {
        if (!empty($usage['iterations']) && is_array($usage['iterations'])) {
            $in = 0;
            $out = 0;
            foreach ($usage['iterations'] as $it) {
                $in += (int) ($it['input_tokens'] ?? 0) + (int) ($it['cache_creation_input_tokens'] ?? 0) + (int) ($it['cache_read_input_tokens'] ?? 0);
                $out += (int) ($it['output_tokens'] ?? 0);
            }
            $result->inputTokens = $in;
            $result->outputTokens = $out;
            return;
        }
        if (isset($usage['input_tokens'])) {
            $result->inputTokens = (int) $usage['input_tokens'] + (int) ($usage['cache_creation_input_tokens'] ?? 0) + (int) ($usage['cache_read_input_tokens'] ?? 0);
        }
        if (isset($usage['output_tokens'])) {
            $result->outputTokens = (int) $usage['output_tokens'];
        }
    }

    private function assertOk(array $response): void
    {
        if ($response['status'] === 200) {
            return;
        }
        $message = Http::errorMessage($response['body']);
        if ($response['status'] === 400 && stripos($message, 'anthropic-beta') !== false) {
            throw new AiException('bad_request', 'beta: ' . $message, false, 400);
        }
        Logger::warning('Anthropic API error', ['status' => $response['status'], 'message' => mb_substr($message, 0, 300)]);
        throw AiException::fromStatus($response['status'], $message, $response['retry_after']);
    }
}
