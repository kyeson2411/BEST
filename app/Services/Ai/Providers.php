<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

use Sibol\Core\Config;

/** Builds the configured provider. Misconfiguration fails safely with a clear message. */
final class Providers
{
    private static ?AiProvider $override = null;

    /** Tests can inject a fake provider. */
    public static function fake(?AiProvider $provider): void
    {
        self::$override = $provider;
    }

    public static function make(): AiProvider
    {
        if (self::$override !== null) {
            return self::$override;
        }
        $provider = (string) Config::get('ai.provider', 'demo');
        $key = (string) Config::get('ai.api_key', '');
        $model = (string) Config::get('ai.model', '');
        $timeout = (int) Config::get('ai.timeout', 90);
        switch ($provider) {
            case 'anthropic':
                if ($key === '') {
                    throw new AiException('not_configured', t('The AI service is not set up yet: an administrator needs to add AI_API_KEY to the server configuration.'));
                }
                return new AnthropicProvider($key, $model ?: null, $timeout);
            case 'openai_compatible':
                $base = (string) Config::get('ai.base_url', '');
                if ($base === '' || $model === '') {
                    throw new AiException('not_configured', t('The AI service is not set up yet: an administrator needs to set AI_BASE_URL and AI_MODEL.'));
                }
                return new OpenAiCompatibleProvider($base, $key, $model, $timeout);
            case 'demo':
                return new DemoProvider();
        }
        throw new AiException('not_configured', t('AI_PROVIDER has an unknown value. Use demo, anthropic or openai_compatible.'));
    }

    /** Short label for the interface, e.g. "Demo mode" or "Claude (claude-opus-5-5)". */
    public static function label(): string
    {
        try {
            $p = self::make();
        } catch (AiException $e) {
            return t('Not configured');
        }
        if ($p->isDemo()) {
            return t('Demo mode (no AI model)');
        }
        return ($p->name() === 'anthropic' ? 'Claude' : t('Configured provider')) . ' (' . $p->model() . ')';
    }
}
