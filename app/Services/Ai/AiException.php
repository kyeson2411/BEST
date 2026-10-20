<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

/**
 * A provider failure with a safe, user-facing message. $code is stable and stored
 * with usage events: not_configured | auth | bad_request | rate_limited | overloaded |
 * server_error | timeout | network | too_large | model_unavailable | invalid_output
 */
final class AiException extends \RuntimeException
{
    public string $errorCode;
    public bool $retryable;
    public int $status;
    public ?int $retryAfter;

    public function __construct(string $errorCode, string $userMessage, bool $retryable = false, int $status = 0, ?int $retryAfter = null)
    {
        parent::__construct($userMessage);
        $this->errorCode = $errorCode;
        $this->retryable = $retryable;
        $this->status = $status;
        $this->retryAfter = $retryAfter;
    }

    public static function fromStatus(int $status, string $providerMessage, ?int $retryAfter = null): self
    {
        switch (true) {
            case $status === 401 || $status === 403:
                return new self('auth', t('The AI service rejected the school\'s credentials. An administrator needs to check the API key.'), false, $status);
            case $status === 404:
                return new self('model_unavailable', t('The configured AI model is not available. An administrator needs to check AI_MODEL.'), false, $status);
            case $status === 413:
                return new self('too_large', t('That request was too large for the AI service. Try a shorter question or a smaller material.'), false, $status);
            case $status === 429:
                return new self('rate_limited', t('The AI service is busy right now. Try again in a minute.'), true, $status, $retryAfter);
            case $status === 529:
                return new self('overloaded', t('The AI service is overloaded at the moment. Try again shortly.'), true, $status, $retryAfter);
            case $status >= 500:
                return new self('server_error', t('The AI service had a problem. Try again in a moment.'), true, $status, $retryAfter);
            default:
                return new self('bad_request', t('The AI service could not process this request.') . ($providerMessage !== '' && \Sibol\Core\Config::isLocal() ? ' (' . mb_substr($providerMessage, 0, 200) . ')' : ''), false, $status);
        }
    }
}
