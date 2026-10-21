<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

/**
 * Keeps citations honest: markers for sources that were not supplied are removed,
 * and each kept citation is stored with the real passage it points to.
 */
final class Citations
{
    /**
     * @param list<array<string,mixed>> $sources numbered passages that were actually sent
     * @return array{text:string,citations:list<array<string,mixed>>}
     */
    public static function resolve(string $text, array $sources): array
    {
        $byNumber = [];
        foreach ($sources as $s) {
            $byNumber[(int) $s['n']] = $s;
        }
        $used = [];
        $clean = (string) preg_replace_callback('/\[(\d{1,2}(?:\s*,\s*\d{1,2})*)\]/', static function (array $m) use ($byNumber, &$used): string {
            $kept = [];
            foreach (preg_split('/\s*,\s*/', $m[1]) ?: [] as $n) {
                $n = (int) $n;
                if (isset($byNumber[$n])) {
                    $kept[] = $n;
                    $used[$n] = true;
                }
            }
            return $kept ? '[' . implode(', ', $kept) . ']' : '';
        }, self::normalizeMarkers($text));
        // Tidy spaces left behind by removed markers.
        $clean = (string) preg_replace('/ +([.,;:!?])/', '$1', $clean);

        $citations = [];
        ksort($used);
        foreach (array_keys($used) as $n) {
            $s = $byNumber[$n];
            $citations[] = [
                'ref_no' => $n,
                'material_id' => (int) $s['material_id'],
                'material_version_id' => (int) $s['version_id'],
                'chunk_id' => (int) $s['chunk_id'],
                'title' => (string) $s['title'],
                'page_from' => $s['page_from'],
                'page_to' => $s['page_to'],
                'section_title' => $s['section_title'],
                'extension' => $s['extension'] ?? null,
                'excerpt' => self::excerpt((string) $s['content'], self::citingSentences($clean, $n)),
            ];
        }
        return ['text' => trim($clean), 'citations' => $citations];
    }

    /**
     * Source markers written with other brackets, as some models do (【1】, 【1†source】, ［2］, 〔1, 2〕), become [1],
     * so they are checked and linked like any other. Otherwise the source would be dropped, and an answer that used
     * the notes would be labelled "Not found in your class materials".
     */
    public static function normalizeMarkers(string $text): string
    {
        return (string) preg_replace_callback(
            '/[【［〔]\s*(\d{1,2}(?:\s*[,，、]\s*\d{1,2})*)\s*(?:†[^】］〕\n]*)?[】］〕]/u',
            static fn (array $m): string => '[' . implode(', ', preg_split('/\s*[,，、]\s*/u', $m[1]) ?: []) . ']',
            $text
        );
    }

    /** The answer sentences that carry marker [n]. */
    private static function citingSentences(string $text, int $n): string
    {
        $out = [];
        foreach (preg_split('/(?<=[.!?])\s+/u', $text) ?: [] as $sentence) {
            if (preg_match('/\[(?:\d{1,2}\s*,\s*)*' . $n . '(?:\s*,\s*\d{1,2})*\]/', $sentence)) {
                $out[] = $sentence;
            }
        }
        return implode(' ', $out);
    }

    /**
     * The passage sentence that best overlaps the citing text, as a short quotation. Heading lines (a PDF page often
     * starts with them: "Patterns beyond simple dominance") are not sentences and are never quoted.
     */
    public static function excerpt(string $passage, string $citing): string
    {
        $passage = self::withoutHeadings($passage);
        $sentences = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+|\n+/u', $passage) ?: []), static fn ($s) => mb_strlen($s) > 20));
        if (!$sentences) {
            return self::trimTo($passage, 240);
        }
        $words = self::words($citing);
        $best = $sentences[0];
        $bestScore = -1;
        foreach ($sentences as $sentence) {
            $score = count(array_intersect(self::words($sentence), $words));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $sentence;
            }
        }
        return self::trimTo($best, 240);
    }

    /**
     * The text without its heading lines: short lines (ten words at most) that start with a capital letter or a
     * digit, end without sentence punctuation, and are followed by a line that starts with a capital letter, as a
     * heading is followed by its first sentence. List items and wrapped sentences stay.
     */
    public static function withoutHeadings(string $text): string
    {
        $lines = preg_split('/\n/u', str_replace("\r", '', $text)) ?: [];
        $keep = [];
        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            $next = '';
            for ($j = $i + 1; $j < count($lines) && $next === ''; $j++) {
                $next = trim($lines[$j]);
            }
            $heading = $trimmed !== ''
                && (str_starts_with($trimmed, '#')
                    || (preg_match('/^[\p{Lu}\d]/u', $trimmed) && !preg_match('/^\d+[.)]\s/u', $trimmed)
                        && !preg_match('/[.!?:;,"”\')\]]$/u', $trimmed)
                        && count(preg_split('/\s+/u', $trimmed) ?: []) <= 10
                        && preg_match('/^[\p{Lu}"“]/u', $next)));
            if (!$heading) {
                $keep[] = $line;
            }
        }
        return implode("\n", $keep);
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        $stop = ['the', 'and', 'for', 'that', 'with', 'this', 'from', 'are', 'was', 'were', 'has', 'have', 'into', 'its', 'can', 'your', 'you', 'their', 'they', 'which', 'when', 'what', 'how', 'why', 'also', 'than', 'then', 'there', 'these', 'those', 'each', 'more', 'most', 'some', 'such', 'only', 'other', 'about'];
        preg_match_all('/\p{L}{3,}/u', mb_strtolower($text), $m);
        return array_values(array_unique(array_diff($m[0], $stop)));
    }

    private static function trimTo(string $text, int $limit): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space ? mb_substr($cut, 0, $space) : $cut, ' ,;:') . '…';
    }
}
