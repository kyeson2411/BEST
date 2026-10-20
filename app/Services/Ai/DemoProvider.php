<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

/**
 * Marker provider for demo mode. It never calls a model: the companion and the
 * generators branch on isDemo() and build clearly labelled, extractive output from
 * real retrieved passages (see DemoResponder), so nothing is fabricated.
 */
final class DemoProvider implements AiProvider
{
    public function name(): string
    {
        return 'demo';
    }

    public function model(): string
    {
        return 'demo-extractive';
    }

    public function isDemo(): bool
    {
        return true;
    }

    public function complete(AiRequest $request, ?callable $onText = null, ?callable $shouldStop = null): AiResult
    {
        throw new AiException('not_configured', t('Demo mode does not write new text. Connect an AI provider to use this feature.'));
    }
}
