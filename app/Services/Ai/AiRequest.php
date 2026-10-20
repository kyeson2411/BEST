<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

/** Provider-neutral request. Messages are [{role: user|assistant, content: string}]. */
final class AiRequest
{
    public string $system;
    /** @var list<array{role:string,content:string}> */
    public array $messages;
    public string $feature;
    public int $maxTokens;
    public string $effort;

    public function __construct(string $system, array $messages, string $feature, int $maxTokens = 16000, string $effort = 'medium')
    {
        $this->system = $system;
        $this->messages = $messages;
        $this->feature = $feature;
        $this->maxTokens = $maxTokens;
        $this->effort = $effort;
    }
}
