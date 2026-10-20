<?php

declare(strict_types=1);

namespace Sibol\Services;

/** Finds interface strings in the code and compares them with a translation file. */
final class TranslationAudit
{
    /** @return array<string,list<string>> English source string => files that use it */
    public static function sourceStrings(): array
    {
        $string = "'((?:[^'\\\\]|\\\\.)*)'";
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . '/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(BASE_PATH) + 1));
            if (preg_match_all('/(?<![\w>$])t\(\s*' . $string . '/', $src, $m)) {
                foreach ($m[1] as $s) {
                    $found[stripslashes($s)][$rel] = true;
                }
            }
            if (preg_match_all('/(?<![\w>$])tp\(\s*' . $string . '\s*,\s*' . $string . '/', $src, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $found[stripslashes($hit[1])][$rel] = true;
                    $found[stripslashes($hit[2])][$rel] = true;
                }
            }
        }
        ksort($found);
        return array_map(static fn (array $f): array => array_keys($f), $found);
    }

    /** @return array<string,string> */
    public static function translations(string $locale): array
    {
        $file = BASE_PATH . '/lang/' . $locale . '.php';
        $lines = is_file($file) ? require $file : [];
        return is_array($lines) ? $lines : [];
    }

    /** @return list<string> placeholders such as :count, in order of first use */
    public static function placeholders(string $text): array
    {
        preg_match_all('/:([a-z_]+)/', $text, $m);
        $names = array_values(array_unique($m[1]));
        sort($names);
        return $names;
    }
}
