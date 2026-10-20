<?php

declare(strict_types=1);

use Sibol\Core\Auth;
use Sibol\Core\Config;
use Sibol\Core\Csrf;
use Sibol\Core\Dates;
use Sibol\Core\Markdown;
use Sibol\Core\Session;
use Sibol\Core\Translator;
use Sibol\Core\View;

/** HTML-escape for text and attribute contexts. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Translate an English source string; :name placeholders are replaced from $params. */
function t(string $text, array $params = []): string
{
    return Translator::get($text, $params);
}

/** Plural-aware translate. The count is available as :count. */
function tp(string $singular, string $plural, int $count, array $params = []): string
{
    return Translator::plural($singular, $plural, $count, $params);
}

/** URL path of the public/ folder, e.g. "/BEST/public" (empty when served at the web root). */
function base_path_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_NAME'])) {
        $dir = str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME']));
        $base = rtrim($dir, '/');
    } else {
        $base = rtrim((string) parse_url((string) Config::get('app.url', ''), PHP_URL_PATH), '/');
    }
    return $base;
}

/** Root-relative URL for an app path. */
function url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    $qs = $query ? '?' . http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== '')) : '';
    return base_path_url() . ($path === '/' ? '/' : $path) . ($qs === '?' ? '' : $qs);
}

/** Absolute URL (for links an admin copies, such as invitations). */
function absolute_url(string $path = '/'): string
{
    $configured = (string) Config::get('app.url', '');
    if ($configured !== '') {
        return rtrim($configured, '/') . '/' . ltrim($path, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

/** Previously submitted value (after a failed validation redirect). */
function old(string $key, $default = '')
{
    $value = Session::old($key);
    return $value === null ? $default : $value;
}

/** @return array<string,string> */
function errors(): array
{
    return Session::errors();
}

function field_error(string $name): ?string
{
    return Session::errors()[$name] ?? null;
}

function auth_user(): ?array
{
    return Auth::user();
}

function home_path(?string $role = null): string
{
    $role = $role ?? Auth::role();
    switch ($role) {
        case 'student':
            return '/student';
        case 'teacher':
            return '/teacher';
        case 'admin':
            return '/admin';
        default:
            return '/login';
    }
}

/** Lucide icon from the sprite. Decorative unless a label is given. */
function icon(string $name, string $class = '', ?string $label = null): string
{
    $a11y = $label === null ? 'aria-hidden="true" focusable="false"' : 'role="img" aria-label="' . e($label) . '"';
    return '<svg class="icon ' . e($class) . '" ' . $a11y . '><use href="' . e(asset('icons/sprite.svg')) . '#i-' . e($name) . '"></use></svg>';
}

function fmt_date(?string $utc, string $style = 'date'): string
{
    return Dates::format($utc, $style);
}

function fmt_rel(?string $utc): string
{
    return Dates::relative($utc);
}

/** <time> element with machine-readable value. */
function time_tag(?string $utc, string $style = 'date', bool $relative = false): string
{
    if (!$utc) {
        return '';
    }
    $label = $relative ? Dates::relative($utc) : Dates::format($utc, $style);
    $title = $relative ? ' title="' . e(Dates::format($utc, 'datetime')) . '"' : '';
    return '<time datetime="' . e(Dates::format($utc, 'iso')) . '"' . $title . '>' . e($label) . '</time>';
}

function role_label(string $role): string
{
    switch ($role) {
        case 'admin':
            return t('School administrator');
        case 'teacher':
            return t('Teacher');
        case 'student':
            return t('Student');
        default:
            return $role;
    }
}

/** Plain-language assignment status ("Scheduled" when published but not yet open). @return array{0:string,1:string} */
function assignment_status(array $a): array
{
    switch ($a['status'] ?? '') {
        case 'draft':
            return [t('Draft'), 'neutral'];
        case 'published':
            if (!empty($a['opens_at']) && $a['opens_at'] > \Sibol\Core\Db::now()) {
                return [t('Scheduled'), 'amber'];
            }
            return [t('Published'), 'leaf'];
        case 'closed':
            return [t('Closed'), 'neutral'];
        case 'archived':
            return [t('Archived'), 'neutral'];
    }
    return [(string) ($a['status'] ?? ''), 'neutral'];
}

function user_name(?array $user, bool $full = true): string
{
    if (!$user) {
        return t('Unknown user');
    }
    $given = (string) ($user['preferred_name'] ?? '') ?: (string) ($user['given_name'] ?? '');
    return $full ? trim($given . ' ' . (string) ($user['family_name'] ?? '')) : $given;
}

function initials(?array $user): string
{
    if (!$user) {
        return '?';
    }
    $a = mb_substr((string) (($user['preferred_name'] ?? '') ?: ($user['given_name'] ?? '')), 0, 1);
    $b = mb_substr((string) ($user['family_name'] ?? ''), 0, 1);
    return mb_strtoupper($a . $b) ?: '?';
}

/** Lower-cases only the first character (for mid-sentence use of a label). */
function lcfirst_u(string $text): string
{
    return mb_strtolower(mb_substr($text, 0, 1)) . mb_substr($text, 1);
}

/** "5/7" with a percentage, for released scores. */
function score_html($score, $possible, bool $withPercent = true): string
{
    if ($score === null) {
        return '<span class="score">—</span>';
    }
    $pct = percent($score, $possible);
    return '<span class="score"><strong>' . e(fmt_number($score, 2)) . '</strong><span class="score__of">/' . e(fmt_number($possible, 2)) . '</span>'
        . ($withPercent && $pct !== null ? '<span class="score__pct">' . e(fmt_number($pct)) . '%</span>' : '') . '</span>';
}

function str_limit(?string $text, int $limit = 120): string
{
    $text = trim((string) $text);
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    $cut = mb_substr($text, 0, $limit - 1);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > $limit * 0.6) {
        $cut = mb_substr($cut, 0, $space);
    }
    return rtrim($cut, " ,;:.-") . '…';
}

function markdown(?string $text, array $opts = []): string
{
    return Markdown::render($text, $opts);
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked($condition): string
{
    return $condition ? ' checked' : '';
}

function current_path(): string
{
    return (string) View::shared('current_path', '/');
}

/** True when the current page is $prefix or below it. */
function path_is(string $prefix, bool $exact = false): bool
{
    $path = current_path();
    if ($exact) {
        return $path === $prefix;
    }
    return $path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/');
}

function percent($part, $whole, int $decimals = 0): ?float
{
    $whole = (float) $whole;
    if ($whole <= 0) {
        return null;
    }
    return round(((float) $part / $whole) * 100, $decimals);
}

function fmt_number($value, int $decimals = 0): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $value = (float) $value;
    if ($decimals === 0) {
        return number_format($value);
    }
    return rtrim(rtrim(number_format($value, $decimals), '0'), '.');
}

function setting(string $key, $default = null)
{
    $orgId = Auth::orgId();
    if ($orgId === null) {
        return $default;
    }
    return \Sibol\Services\Settings::get($orgId, $key, $default);
}

/** Render a partial from app/Views/partials. */
function partial(string $name, array $data = []): string
{
    return View::file('partials/' . $name, $data);
}

/** Status word with tone; colour is never the only signal. */
function badge(string $label, string $tone = 'neutral', ?string $iconName = null): string
{
    $icon = $iconName ? icon($iconName, 'icon--xs') : '';
    return '<span class="badge badge--' . e($tone) . '">' . $icon . e($label) . '</span>';
}

/**
 * Accessible form field: label, control, hint and error wired with aria-describedby.
 * $type: text|email|password|number|date|datetime-local|url|textarea|select|search|time
 * $opts: value, hint, required, options (for select: value => label), attrs (array), rows, placeholder, class,
 *        isolated (ignore old input and errors: for the other forms on a page that has several)
 */
function field(string $type, string $name, string $label, array $opts = []): string
{
    $id = $opts['id'] ?? 'f-' . preg_replace('/[^a-z0-9_-]/i', '-', $name);
    $value = array_key_exists('value', $opts) ? $opts['value'] : '';
    $isolated = !empty($opts['isolated']);
    $oldValue = $isolated ? null : Session::old($name);
    if ($oldValue !== null && !is_array($oldValue) && $type !== 'password') {
        $value = $oldValue;
    }
    $error = $opts['error'] ?? ($isolated ? null : field_error($name));
    $hint = $opts['hint'] ?? null;
    $describedBy = [];
    if ($hint) {
        $describedBy[] = $id . '-hint';
    }
    if ($error) {
        $describedBy[] = $id . '-error';
    }
    $attrs = ['id' => $id, 'name' => $name];
    if (!empty($opts['required'])) {
        $attrs['required'] = true;
    }
    if ($describedBy) {
        $attrs['aria-describedby'] = implode(' ', $describedBy);
    }
    if ($error) {
        $attrs['aria-invalid'] = 'true';
    }
    if (!empty($opts['placeholder'])) {
        $attrs['placeholder'] = $opts['placeholder'];
    }
    foreach ((array) ($opts['attrs'] ?? []) as $k => $v) {
        $attrs[$k] = $v;
    }
    $attrString = static function (array $attrs): string {
        $out = '';
        foreach ($attrs as $k => $v) {
            if ($v === false || $v === null) {
                continue;
            }
            $out .= $v === true ? ' ' . e($k) : ' ' . e($k) . '="' . e($v) . '"';
        }
        return $out;
    };

    $class = 'input' . (!empty($opts['class']) ? ' ' . $opts['class'] : '');
    if ($type === 'textarea') {
        $attrs['rows'] = $opts['rows'] ?? 5;
        $control = '<textarea class="' . e($class . ' textarea') . '"' . $attrString($attrs) . '>' . e($value) . '</textarea>';
    } elseif ($type === 'select') {
        $control = '<select class="' . e($class . ' select') . '"' . $attrString($attrs) . '>';
        foreach ((array) ($opts['options'] ?? []) as $optValue => $optLabel) {
            $control .= '<option value="' . e($optValue) . '"' . selected($optValue, $value) . '>' . e($optLabel) . '</option>';
        }
        $control .= '</select>';
    } else {
        $attrs['type'] = $type;
        if ($type !== 'password') {
            $attrs['value'] = (string) $value;
        }
        $control = '<input class="' . e($class) . '"' . $attrString($attrs) . '>';
    }

    $required = !empty($opts['required']) ? '' : (!empty($opts['optional_label']) ? ' <span class="field__optional">' . e(t('optional')) . '</span>' : '');
    $html = '<div class="field' . ($error ? ' field--error' : '') . (!empty($opts['wrap_class']) ? ' ' . e($opts['wrap_class']) : '') . '">';
    $html .= '<label class="field__label" for="' . e($id) . '">' . e($label) . $required . '</label>';
    if ($hint) {
        $html .= '<p class="field__hint" id="' . e($id) . '-hint">' . e($hint) . '</p>';
    }
    $html .= $control;
    if ($error) {
        $html .= '<p class="field__error" id="' . e($id) . '-error">' . icon('circle-alert', 'icon--xs') . e($error) . '</p>';
    }
    return $html . '</div>';
}

/** Checkbox row with label and optional hint. */
function checkbox(string $name, string $label, bool $isChecked = false, ?string $hint = null, string $value = '1', ?string $id = null, bool $isolated = false): string
{
    $id = $id ?? 'f-' . preg_replace('/[^a-z0-9_-]/i', '-', $name . '-' . $value);
    $oldValue = $isolated ? null : Session::old($name);
    if (Session::errors() && $oldValue !== null) {
        $isChecked = (string) $oldValue === $value;
    }
    $html = '<div class="check">';
    $html .= '<input type="checkbox" class="check__input" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"' . checked($isChecked)
        . ($hint ? ' aria-describedby="' . e($id) . '-hint"' : '') . '>';
    $html .= '<label class="check__label" for="' . e($id) . '">' . e($label);
    if ($hint) {
        $html .= '<span class="check__hint" id="' . e($id) . '-hint">' . e($hint) . '</span>';
    }
    return $html . '</label></div>';
}

/** JSON for embedding in a data- attribute. */
function json_attr($data): string
{
    return e((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
