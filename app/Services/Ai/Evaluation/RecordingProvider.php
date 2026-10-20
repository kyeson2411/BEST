<?php

declare(strict_types=1);

namespace Sibol\Services\Ai\Evaluation;

use Sibol\Services\Ai\AiProvider;
use Sibol\Services\Ai\AiRequest;
use Sibol\Services\Ai\AiResult;

/**
 * Passes each call to the real provider and keeps what the AI check needs: how many calls
 * were made, the passage numbers the model was given, and the token counts. It keeps no text.
 */
final class RecordingProvider implements AiProvider
{
    private AiProvider $inner;
    public int $calls = 0;
    public int $inputTokens = 0;
    public int $outputTokens = 0;
    public string $servedModel = '';
    public bool $truncated = false;
    /** @var list<int>|null passage numbers in the last request */
    public ?array $sourceNumbers = null;

    public function __construct(AiProvider $inner)
    {
        $this->inner = $inner;
    }

    public function reset(): void
    {
        $this->calls = 0;
        $this->inputTokens = 0;
        $this->outputTokens = 0;
        $this->servedModel = '';
        $this->truncated = false;
        $this->sourceNumbers = null;
    }

    public function name(): string
    {
        return $this->inner->name();
    }

    public function model(): string
    {
        return $this->inner->model();
    }

    public function isDemo(): bool
    {
        return $this->inner->isDemo();
    }

    public function complete(AiRequest $request, ?callable $onText = null, ?callable $shouldStop = null): AiResult
    {
        $this->calls++;
        $last = $request->messages[count($request->messages) - 1]['content'] ?? '';
        preg_match_all('/<source id="(\d+)"/', (string) $last, $m);
        $this->sourceNumbers = array_map('intval', $m[1]);
        $result = $this->inner->complete($request, $onText, $shouldStop);
        $this->inputTokens += $result->inputTokens;
        $this->outputTokens += $result->outputTokens;
        $this->servedModel = $result->model;
        $this->truncated = $this->truncated || $result->truncated;
        return $result;
    }
}
