<?php

declare(strict_types=1);

namespace Sibol\Core;

final class View
{
    /** @var array<string,mixed> */
    private static array $shared = [];

    /** @param mixed $value */
    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @return mixed */
    public static function shared(string $key, $default = null)
    {
        return self::$shared[$key] ?? $default;
    }

    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::file($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::file($layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string,mixed> $data */
    public static function file(string $template, array $data = []): string
    {
        $path = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException('View not found: ' . $template);
        }
        $vars = array_merge(self::$shared, $data);
        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__path, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                include $__path;
            })($path, $vars);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
