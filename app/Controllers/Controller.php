<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Auth;
use Sibol\Core\HttpException;
use Sibol\Core\Request;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Core\ValidationException;
use Sibol\Core\Validator;
use Sibol\Core\View;

abstract class Controller
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /** @return array<string,mixed> the signed-in user (routes guarantee one exists) */
    protected function user(): array
    {
        $user = Auth::user();
        if ($user === null) {
            throw new HttpException(401, t('Sign in to continue.'));
        }
        return $user;
    }

    protected function orgId(): int
    {
        return (int) $this->user()['organization_id'];
    }

    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        return Response::html(View::render($template, $data, $layout));
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($path);
    }

    protected function json($data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    /**
     * Validates request input; on failure redirects back with errors (or 422 JSON).
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     * @return array<string,mixed>
     */
    protected function validate(array $rules, array $labels = [], ?string $redirectTo = null, ?array $input = null): array
    {
        $input = $input ?? $this->request->all();
        $validator = Validator::make($input, $rules, $labels);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors(), $input, $redirectTo);
        }
        return $validator->validated();
    }

    /** Throw a validation error for one field (used for checks that need the database). */
    protected function fail(string $field, string $message, ?string $redirectTo = null): void
    {
        throw new ValidationException([$field => $message], $this->request->all(), $redirectTo);
    }

    protected function notFound(): HttpException
    {
        return HttpException::notFound();
    }

    protected function page(): int
    {
        return max(1, (int) ($this->request->int('page') ?? 1));
    }
}
