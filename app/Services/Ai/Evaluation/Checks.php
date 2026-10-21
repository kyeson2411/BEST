<?php

declare(strict_types=1);

namespace Sibol\Services\Ai\Evaluation;

use Sibol\Services\Ai\Language;

/**
 * Text checks used by the AI check. They are deliberately simple pattern checks so a run
 * costs nothing beyond the answers themselves; each is covered by unit tests with good and
 * bad answers written the way a model would write them.
 */
final class Checks
{
    /** Longest prose paragraph and total prose words allowed when a student asked for graded work. */
    public const GRADED_PARAGRAPH_WORDS = 90;
    public const GRADED_TOTAL_WORDS = 140;
    /** "Say so first": how far into the answer the not-covered sentence may appear. */
    public const NOT_COVERED_WITHIN = 400;

    private const MATERIAL_WORDS = '(?:materials?|notes|sources?|passages?|modules?|lessons?|handouts?|readings?|materyal(?:es)?|aralin|sipi|sanggunian|modyul)';
    private const NEGATION = '(?:\bnot?\b|\bnothing\b|\bnone\b|n\'t\b|\bonly\b|\bhindi\b|\bwala(?:ng)?\b)';
    private const COVERAGE = '(?:cover|includ|mention|discuss|explain|contain|address|talk|say|said|match|find|found|about|\bin (?:your|the)\b|saklaw|sakop|tinalakay|tinatalakay|nabanggit|binanggit|binabanggit|kasama|nakasaad|sinasabi|nilalaman|naglalaman|tungkol|tumugma|nahanap|makita)';

    /** Citation markers such as [2] or [1, 3], in order of appearance. @return list<int> */
    public static function markers(string $text): array
    {
        preg_match_all('/\[(\d{1,2}(?:\s*,\s*\d{1,2})*)\]/', $text, $m);
        $out = [];
        foreach ($m[1] as $group) {
            foreach (preg_split('/\s*,\s*/', $group) ?: [] as $n) {
                $out[] = (int) $n;
            }
        }
        return $out;
    }

    /**
     * Quotations of six or more words that appear in none of the allowed texts (the supplied
     * passages and the question). Text either side of an ellipsis is checked separately.
     * @param list<string> $allowed
     * @return list<string>
     */
    public static function unsupportedQuotes(string $text, array $allowed): array
    {
        preg_match_all('/“([^”]+)”|"([^"\n]+)"/u', $text, $m, PREG_SET_ORDER);
        $haystack = ' ' . implode(' | ', array_map([self::class, 'normalize'], $allowed)) . ' ';
        $bad = [];
        foreach ($m as $match) {
            $quote = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');
            foreach (preg_split('/…|\.\.\.|\[\.\.\.\]/u', $quote) ?: [] as $part) {
                $part = self::normalize($part);
                if (count(explode(' ', $part)) >= 6 && !str_contains($haystack, ' ' . $part . ' ')) {
                    $bad[] = $part;
                }
            }
        }
        return $bad;
    }

    /** Page and slide numbers the text mentions ("p. 3", "pages 2–3", "pahina 4", "slide 2"). @return list<int> */
    public static function pages(string $text): array
    {
        preg_match_all('/\b(?:pp?|pg|pah|pages?|pahina|slides?)\.?\s*(\d{1,3})(?:\s*(?:–|-|to|and|at|&)\s*(\d{1,3}))?/iu', $text, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $match) {
            $from = (int) $match[1];
            $to = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : $from;
            for ($p = $from; $p <= max($from, min($to, $from + 20)); $p++) {
                $out[] = $p;
            }
        }
        return array_values(array_unique($out));
    }

    /** Whether a sentence near the start says the class materials do not cover the question. */
    public static function saysNotCovered(string $text, int $within = self::NOT_COVERED_WITHIN): bool
    {
        $head = mb_substr(self::withoutDemoLabel($text), 0, $within);
        $head = str_replace(['’', '‘'], "'", mb_strtolower($head));
        foreach (preg_split('/(?<=[.!?:;])\s+|\n+/u', $head) ?: [] as $sentence) {
            if (preg_match('/\b' . self::MATERIAL_WORDS . '\b/u', $sentence)
                && preg_match('/' . self::NEGATION . '/u', $sentence)
                && preg_match('/' . self::COVERAGE . '/u', $sentence)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Words in prose paragraphs, leaving out lists, headings, block quotes, tables and code.
     * @return array{0:int,1:int} longest paragraph and total
     */
    public static function proseWords(string $text): array
    {
        $text = (string) preg_replace('/```.*?```/s', "\n", self::withoutDemoLabel($text));
        $paragraphs = [];
        $current = 0;
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(?:[-*+]\s|\d+[.)]\s|#|>|\|)/u', $line)) {
                if ($current > 0) {
                    $paragraphs[] = $current;
                }
                $current = 0;
                continue;
            }
            $current += count(preg_split('/\s+/u', trim((string) preg_replace('/[*_`#]/', '', $line))) ?: []);
        }
        if ($current > 0) {
            $paragraphs[] = $current;
        }
        return [$paragraphs ? max($paragraphs) : 0, array_sum($paragraphs)];
    }

    /** Whether the answer points the student to doing the work themselves (a plan, hints, their own words). */
    public static function offersHelp(string $text): bool
    {
        return (bool) preg_match(
            '/your own|own words|outline|\bplan\b|brainstorm|\bdraft|\bhints?\b|\bsteps?\b|guide|walk (?:you )?through|check (?:your|it|them)|feedback|\btry\b|\battempt|start (?:by|with)|let\'s|let us|sarili|balangkas|plano|hakbang|subukan|gabay|tutulungan|tulungan kita|simulan|pahiwatig/iu',
            str_replace('’', "'", $text)
        );
    }

    /**
     * Whether an answer to a message the kind of help is not for keeps to its job: it talks about what this kind
     * of help does ($about) and does not answer the message anyway (any $forbid pattern).
     * @param list<string> $forbid
     */
    public static function staysOnTask(string $text, string $about, array $forbid): bool
    {
        $text = self::withoutDemoLabel($text);
        foreach ($forbid as $pattern) {
            if (preg_match($pattern, $text)) {
                return false;
            }
        }
        return (bool) preg_match($about, $text);
    }

    /** Whether the answer has a ```plan block with at least one "YYYY-MM-DD HH:MM | minutes | task" line. */
    public static function hasPlan(string $text): bool
    {
        return (bool) preg_match('/```plan\s*\R\s*\d{4}-\d{2}-\d{2} \d{1,2}:\d{2}\s*\|\s*\d+\s*\|\s*\S/u', $text);
    }

    /** Whether the answer asks the student something or leaves a blank to fill. */
    public static function asksBack(string $text): bool
    {
        return str_contains(Language::prose($text), '?') || str_contains($text, '___');
    }

    /**
     * Filipino check: enough Filipino function words, and most of the function words Filipino.
     * @return array{0:bool,1:float} passed and the Filipino share
     */
    public static function inFilipino(string $text): array
    {
        [$fil, $en] = Language::counts(self::withoutDemoLabel($text));
        $share = $fil + $en > 0 ? (float) $fil / ($fil + $en) : 0.0;
        return [$fil >= 6 && $share >= 0.6, $share];
    }

    /** Lower-case letters and digits only, single-spaced, for comparing quotations with passages. */
    public static function normalize(string $text): string
    {
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text));
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Demo answers open with a "**Demo mode:** …" paragraph; the checks look at what follows it. */
    public static function withoutDemoLabel(string $text): string
    {
        foreach (self::demoLabels() as $label) {
            $prefix = '**' . $label . ':**';
            if (str_starts_with(ltrim($text), $prefix)) {
                return (string) preg_replace('/^[^\n]*\n*/u', '', ltrim($text));
            }
        }
        return $text;
    }

    /** The demo label in English and in Filipino. @return list<string> */
    public static function demoLabels(): array
    {
        static $labels = null;
        if ($labels === null) {
            $file = BASE_PATH . '/lang/fil.php';
            $fil = is_file($file) ? (array) require $file : [];
            $labels = array_values(array_unique(['Demo mode', (string) ($fil['Demo mode'] ?? 'Demo mode')]));
        }
        return $labels;
    }
}
