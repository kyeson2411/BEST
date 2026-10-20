<?php

declare(strict_types=1);

namespace Sibol\Core;

final class HttpException extends \RuntimeException
{
    public int $status;

    public function __construct(int $status, string $message = '')
    {
        parent::__construct($message, $status);
        $this->status = $status;
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = ''): self
    {
        return new self(403, $message);
    }
}
