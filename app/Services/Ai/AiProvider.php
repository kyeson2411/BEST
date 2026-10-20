<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

interface AiProvider
{
    public function name(): string;

    public function model(): string;

    /** True for the built-in demo responder, which never calls an external model. */
    public function isDemo(): bool;

    /**
     * Runs one model call. When $onText is given the response is streamed and $onText
     * receives each text fragment. $shouldStop is polled while streaming; returning true cancels.
     *
     * @param callable(string):void|null $onText
     * @param callable():bool|null $shouldStop
     * @throws AiException
     */
    public function complete(AiRequest $request, ?callable $onText = null, ?callable $shouldStop = null): AiResult;
}
