<?php

declare(strict_types=1);

namespace Sibol\Services\Ai\Evaluation;

use Sibol\Services\Ai\AiProvider;
use Sibol\Services\Ai\AiRequest;
use Sibol\Services\Ai\AiResult;

/**
 * Returns prepared replies instead of calling a model. The AI check uses it to simulate a
 * provider refusal (which cannot be triggered safely on purpose); tests use it to stand in
 * for a model. Like the Claude adapter, it streams the text and drops it on a refusal.
 */
final class ScriptedProvider implements AiProvider
{
    /** @var callable(AiRequest):AiResult */
    private $reply;
    private string $model;

    /** @param callable(AiRequest):AiResult $reply */
    public function __construct(callable $reply, string $model = 'scripted')
    {
        $this->reply = $reply;
        $this->model = $model;
    }

    /** Streams $partial, then stops with stop_reason "refusal". */
    public static function refusing(string $partial): self
    {
        return new self(static function () use ($partial): AiResult {
            $result = new AiResult();
            $result->text = $partial;
            $result->stopReason = 'refusal';
            $result->refused = true;
            return $result;
        });
    }

    public function name(): string
    {
        return 'scripted';
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
        $result = ($this->reply)($request);
        if ($onText !== null && $result->text !== '') {
            $onText($result->text);
        }
        if ($result->refused) {
            $result->text = '';
        }
        $result->model = $result->model !== '' ? $result->model : $this->model;
        return $result;
    }
}
