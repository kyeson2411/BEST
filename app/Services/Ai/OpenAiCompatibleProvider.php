<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

use Sibol\Core\Logger;

/**
 * Any service exposing an OpenAI-style POST {base}/chat/completions endpoint
 * (OpenAI, Groq, or a local Ollama / LM Studio server). Configure AI_BASE_URL and AI_MODEL.
 */
final class OpenAiCompatibleProvider implements AiProvider
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;
    private int $timeout;

    public function __construct(string $baseUrl, string $apiKey, string $model, int $timeout = 90)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeout = max(20, $timeout);
    }

    public function name(): string
    {
        return 'openai_compatible';
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
        $wrapped = $onText === null ? null : static function (string $t) use ($onText, &$emitted): void {
            $emitted = true;
            $onText($t);
        };
        while (true) {
            $attempt++;
            try {
                return $this->once($request, $wrapped, $shouldStop);
            } catch (AiException $e) {
                if (!$e->retryable || $emitted || $attempt >= 3) {
                    throw $e;
                }
                sleep($e->retryAfter !== null ? min(20, max(1, $e->retryAfter)) : $attempt);
            }
        }
    }

    private function once(AiRequest $request, ?callable $onText, ?callable $shouldStop): AiResult
    {
        $started = microtime(true);
        $messages = [['role' => 'system', 'content' => $request->system]];
        foreach ($request->messages as $m) {
            $messages[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => (string) $m['content']];
        }
        $body = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => min($request->maxTokens, 8000),
            'temperature' => 0.4,
        ];
        $headers = $this->apiKey !== '' ? ['Authorization: Bearer ' . $this->apiKey] : [];
        $url = $this->baseUrl . '/chat/completions';
        $result = new AiResult();
        $result->model = $this->model;

        if ($onText === null) {
            $response = Http::postJson($url, $headers, $body, $this->timeout);
            $this->assertOk($response);
            $json = json_decode($response['body'], true);
            $result->text = (string) ($json['choices'][0]['message']['content'] ?? '');
            $result->stopReason = $json['choices'][0]['finish_reason'] ?? null;
            $result->inputTokens = (int) ($json['usage']['prompt_tokens'] ?? 0);
            $result->outputTokens = (int) ($json['usage']['completion_tokens'] ?? 0);
        } else {
            $body['stream'] = true;
            $body['stream_options'] = ['include_usage' => true];
            $response = Http::postJson($url, $headers, $body, $this->timeout, static function (string $event, array $data) use ($result, $onText): void {
                $delta = $data['choices'][0]['delta']['content'] ?? null;
                if (is_string($delta) && $delta !== '') {
                    $result->text .= $delta;
                    $onText($delta);
                }
                if (!empty($data['choices'][0]['finish_reason'])) {
                    $result->stopReason = (string) $data['choices'][0]['finish_reason'];
                }
                $usage = $data['usage'] ?? ($data['x_groq']['usage'] ?? null);
                if (is_array($usage)) {
                    $result->inputTokens = (int) ($usage['prompt_tokens'] ?? $result->inputTokens);
                    $result->outputTokens = (int) ($usage['completion_tokens'] ?? $result->outputTokens);
                }
            }, $shouldStop);
            if ($response['cancelled']) {
                $result->cancelled = true;
            } else {
                $this->assertOk($response);
            }
        }
        $result->truncated = $result->stopReason === 'length';
        $result->refused = $result->stopReason === 'content_filter';
        if ($result->refused) {
            $result->text = '';
        }
        if ($result->outputTokens === 0 && $result->text !== '') {
            $result->outputTokens = (int) ceil(mb_strlen($result->text) / 4); // estimate when the server reports no usage
        }
        $result->latencyMs = (int) round((microtime(true) - $started) * 1000);
        return $result;
    }

    private function assertOk(array $response): void
    {
        if ($response['status'] === 200) {
            return;
        }
        $message = Http::errorMessage($response['body']);
        Logger::warning('OpenAI-compatible API error', ['status' => $response['status'], 'message' => mb_substr($message, 0, 300)]);
        throw AiException::fromStatus($response['status'], $message, $response['retry_after']);
    }
}
