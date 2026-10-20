<?php

declare(strict_types=1);

namespace Sibol\Services;

/** A user-facing extraction problem. $unsupported means "valid file, just not readable for AI". */
final class ExtractionException extends \RuntimeException
{
    public bool $unsupported;

    public function __construct(string $message, bool $unsupported = false)
    {
        parent::__construct($message);
        $this->unsupported = $unsupported;
    }
}
