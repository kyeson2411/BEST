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
        }, $text);
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

    /** The passage sentence that best overlaps the citing text, as a short quotation. */
    public static function excerpt(string $passage, string $citing): string
    {
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
