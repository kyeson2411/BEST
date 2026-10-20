<?php

declare(strict_types=1);

namespace Sibol\Services;

/** A Google sign-in that cannot go ahead. The message is for the person; the reason is for the log and tests. */
final class GoogleSignInError extends \RuntimeException
{
    public string $reason;

    public function __construct(string $message, string $reason)
    {
        parent::__construct($message);
        $this->reason = $reason;
    }
}
