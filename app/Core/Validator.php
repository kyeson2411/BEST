<?php

declare(strict_types=1);

namespace Sibol\Core;

/**
 * Server-side validation. Rules are pipe-separated strings:
 *   required | nullable | string | int | numeric | bool | email | url | date | datetime |
 *   in:a,b,c | min:n | max:n (length for strings, value for numbers) | confirmed | timezone
 *
 *   $v = Validator::make($input, ['title' => 'required|string|max:200']);
 *   if ($v->fails()) { ... $v->errors() ... }
 *   $data = $v->validated();
 */
final class Validator
{
    /** @var array<string,mixed> */
    private array $input;
    /** @var array<string,string> */
    private array $rules;
    /** @var array<string,string> */
    private array $labels;
    /** @var array<string,string> */
    private array $errors = [];
    /** @var array<string,mixed> */
    private array $clean = [];

    private function __construct(array $input, array $rules, array $labels)
    {
        $this->input = $input;
        $this->rules = $rules;
        $this->labels = $labels;
        $this->run();
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,string> $rules
     * @param array<string,string> $labels human field names for messages
     */
    public static function make(array $input, array $rules, array $labels = []): self
    {
        return new self($input, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        return $this->clean;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $this->errors[$field] ?? $message;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $raw = $this->input[$field] ?? null;
            $value = is_string($raw) ? trim(str_replace(["\r\n", "\r"], "\n", $raw)) : $raw;
            $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $isEmpty = $value === null || $value === '' || $value === [];

            if (in_array('bool', $rules, true)) {
                $this->clean[$field] = in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true);
                continue;
            }
            if ($isEmpty) {
                if (in_array('required', $rules, true)) {
                    $this->errors[$field] = t(':field is required.', ['field' => $label]);
                } else {
                    $this->clean[$field] = null;
                }
                continue;
            }

            if (is_array($value) && !in_array('array', $rules, true)) {
                $this->errors[$field] = t(':field is not valid.', ['field' => $label]);
                continue;
            }
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->check($name, $arg, $value, $field, $label);
                if ($error !== null) {
                    $this->errors[$field] = $error;
                    break;
                }
            }
            if (!isset($this->errors[$field])) {
                $this->clean[$field] = $this->cast($rules, $value);
            }
        }
    }

    /** @param mixed $value */
    private function check(string $name, ?string $arg, $value, string $field, string $label): ?string
    {
        $isNumericRule = in_array('int', explode('|', $this->rules[$field]), true) || in_array('numeric', explode('|', $this->rules[$field]), true);
        switch ($name) {
            case 'required':
            case 'nullable':
                return null;
            case 'string':
                return is_string($value) ? null : t(':field must be text.', ['field' => $label]);
            case 'int':
                return (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value))) ? null : t(':field must be a whole number.', ['field' => $label]);
            case 'numeric':
                return is_numeric($value) ? null : t(':field must be a number.', ['field' => $label]);
            case 'email':
                return (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) && mb_strlen($value) <= 190) ? null : t('Enter a valid email address.');
            case 'url':
                return (is_string($value) && filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value)) ? null : t('Enter a full web address starting with http:// or https://.');
            case 'date':
                return (is_string($value) && self::parseDate($value) !== null) ? null : t(':field must be a valid date.', ['field' => $label]);
            case 'datetime':
                return (is_string($value) && self::parseDateTime($value) !== null) ? null : t(':field must be a valid date and time.', ['field' => $label]);
            case 'timezone':
                return in_array($value, \DateTimeZone::listIdentifiers(), true) ? null : t('Choose a valid timezone.');
            case 'in':
                $allowed = explode(',', (string) $arg);
                return in_array((string) $value, $allowed, true) ? null : t('Choose a valid option for :field.', ['field' => mb_strtolower($label)]);
            case 'min':
                if ($isNumericRule) {
                    return (float) $value >= (float) $arg ? null : t(':field must be at least :min.', ['field' => $label, 'min' => $arg]);
                }
                return mb_strlen((string) $value) >= (int) $arg ? null : t(':field must be at least :min characters.', ['field' => $label, 'min' => $arg]);
            case 'max':
                if ($isNumericRule) {
                    return (float) $value <= (float) $arg ? null : t(':field must be :max or less.', ['field' => $label, 'max' => $arg]);
                }
                return mb_strlen((string) $value) <= (int) $arg ? null : t(':field must be :max characters or fewer.', ['field' => $label, 'max' => $arg]);
            case 'confirmed':
                return ($this->input[$field . '_confirmation'] ?? null) === $value ? null : t('The two passwords do not match.');
            case 'array':
                return is_array($value) ? null : t(':field is not valid.', ['field' => $label]);
        }
        throw new \RuntimeException('Unknown validation rule: ' . $name);
    }

    /** @param mixed $value @return mixed */
    private function cast(array $rules, $value)
    {
        if (in_array('int', $rules, true)) {
            return (int) $value;
        }
        if (in_array('numeric', $rules, true)) {
            return (float) $value;
        }
        if (in_array('email', $rules, true)) {
            return mb_strtolower((string) $value);
        }
        return $value;
    }

    public static function parseDate(string $value): ?\DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return ($d && $d->format('Y-m-d') === $value) ? $d : null;
    }

    public static function parseDateTime(string $value): ?\DateTimeImmutable
    {
        foreach (['!Y-m-d\TH:i', '!Y-m-d H:i', '!Y-m-d\TH:i:s', '!Y-m-d H:i:s'] as $format) {
            $d = \DateTimeImmutable::createFromFormat($format, $value);
            if ($d && $d->format(str_replace('!', '', $format)) === $value) {
                return $d;
            }
        }
        return null;
    }
}
