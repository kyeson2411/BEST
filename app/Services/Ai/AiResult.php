<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

final class AiResult
{
    public string $text = '';
    public int $inputTokens = 0;
    public int $outputTokens = 0;
    public string $model = '';
    public ?string $stopReason = null;
    public int $latencyMs = 0;
    /** The provider's safety system declined (Claude stop_reason "refusal"); any partial text must be discarded. */
    public bool $refused = false;
    public bool $cancelled = false;
    /** Output stopped at max_tokens. */
    public bool $truncated = false;
    /** A provider error after some text was already streamed. */
    public ?string $errorCode = null;
}
