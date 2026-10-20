<?php

declare(strict_types=1);

namespace Sibol\Core;

/** Thrown by Controller::validate(); App turns it into a redirect back (or a 422 JSON response). */
final class ValidationException extends \RuntimeException
{
    /** @var array<string,string> */
    public array $errors;
    /** @var array<string,mixed> */
    public array $input;
    public ?string $redirectTo;

    public function __construct(array $errors, array $input = [], ?string $redirectTo = null)
    {
        parent::__construct('Validation failed');
        $this->errors = $errors;
        $this->input = $input;
        $this->redirectTo = $redirectTo;
    }
}
