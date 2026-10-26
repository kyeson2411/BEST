<?php

declare(strict_types=1);

namespace Sibol\Services;

/**
 * Splits extracted units into retrieval chunks of roughly TARGET characters.
 * Chunks never cross a page or section boundary, so every chunk's page/section
 * metadata is exact and citations can point at the right place.
 */
final class Chunker
{
    public const TARGET = 900;
    public const MAX = 1400;

    /**
     * @param list<array{page:int|null,section:string|null,text:string}> $units
     * @return list<array{page:int|null,section:string|null,text:string}>
     */
    public static function chunk(array $units): array
    {
        $chunks = [];
        foreach ($units as $unit) {
            $pieces = self::pieces($unit['text']);
            $buffer = '';
            $carry = '';
            foreach ($pieces as $piece) {
                $candidate = $buffer === '' ? $piece : $buffer . "\n\n" . $piece;
                if ($buffer !== '' && mb_strlen($candidate) > self::TARGET) {
                    $chunks[] = ['page' => $unit['page'], 'section' => $unit['section'], 'text' => $buffer];
                    $carry = self::lastSentence($buffer);
                    $buffer = ($carry !== '' && mb_strlen($carry) < 220 ? $carry . "\n\n" : '') . $piece;
                    continue;
                }
                $buffer = $candidate;
            }
            if (trim($buffer) !== '' && trim($buffer) !== trim($carry)) {
                $chunks[] = ['page' => $unit['page'], 'section' => $unit['section'], 'text' => $buffer];
            }
        }
        return $chunks;
    }

    /**
     * Stored chunks back in reading order, one unit per page or section, without the sentence each chunk carries over
     * from the one before it. Used to show a file's text as a page inside Sibol.
     *
     * @param list<array{page:int|null,section:string|null,text:string}> $chunks in chunk order
     * @return list<array{page:int|null,section:string|null,text:string}>
     */
    public static function rejoin(array $chunks): array
    {
        $units = [];
        $previous = null;
        foreach ($chunks as $chunk) {
            $text = (string) $chunk['text'];
            $last = $units ? count($units) - 1 : null;
            if ($last !== null && $units[$last]['page'] === $chunk['page'] && $units[$last]['section'] === $chunk['section']) {
                $carry = self::lastSentence((string) $previous);
                if ($carry !== '' && strncmp($text, $carry . "\n\n", strlen($carry) + 2) === 0) {
                    $text = substr($text, strlen($carry) + 2);
                }
                $units[$last]['text'] .= "\n\n" . $text;
            } else {
                $units[] = ['page' => $chunk['page'], 'section' => $chunk['section'], 'text' => $text];
            }
            $previous = (string) $chunk['text'];
        }
        return $units;
    }

    /** Paragraphs, with oversized paragraphs split by sentence and then by words. @return list<string> */
    private static function pieces(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($text)) ?: [];
        $out = [];
        foreach ($paragraphs as $para) {
            $para = trim((string) preg_replace('/[ \t]+/', ' ', $para));
            if ($para === '') {
                continue;
            }
            if (mb_strlen($para) <= self::MAX) {
                $out[] = $para;
                continue;
            }
            $sentences = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}\d"“(])/u', $para) ?: [$para];
            $current = '';
            foreach ($sentences as $sentence) {
                if (mb_strlen($sentence) > self::MAX) {
                    foreach (self::hardWrap($sentence) as $part) {
                        $out[] = $part;
                    }
                    continue;
                }
                if ($current !== '' && mb_strlen($current . ' ' . $sentence) > self::TARGET) {
                    $out[] = $current;
                    $current = $sentence;
                } else {
                    $current = $current === '' ? $sentence : $current . ' ' . $sentence;
                }
            }
            if ($current !== '') {
                $out[] = $current;
            }
        }
        return $out;
    }

    /** @return list<string> */
    private static function hardWrap(string $text): array
    {
        $words = preg_split('/\s+/', $text) ?: [];
        $out = [];
        $line = '';
        foreach ($words as $word) {
            if ($line !== '' && mb_strlen($line . ' ' . $word) > self::TARGET) {
                $out[] = $line;
                $line = $word;
            } else {
                $line = $line === '' ? $word : $line . ' ' . $word;
            }
        }
        if ($line !== '') {
            $out[] = $line;
        }
        return $out;
    }

    private static function lastSentence(string $text): string
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [];
        return trim((string) end($parts));
    }
}
