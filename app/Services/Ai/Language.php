<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

/**
 * Rough English/Filipino detection from function words, used for demo-mode replies and by
 * the AI check. Quotations, citation markers, code and maths are ignored, so English passages
 * quoted inside a Filipino answer do not count against it. "at" and "may" are words in both
 * languages and are not counted.
 */
final class Language
{
    private const FILIPINO = ['ang', 'ng', 'mga', 'sa', 'na', 'ay', 'ito', 'iyan', 'iyon', 'yan', 'yon', 'para', 'kung', 'hindi', 'ano',
        'bakit', 'paano', 'po', 'ba', 'ka', 'ko', 'mo', 'siya', 'sila', 'tayo', 'kami', 'kayo', 'natin', 'namin', 'ninyo', 'nila', 'niya',
        'nito', 'niyan', 'naman', 'lang', 'lamang', 'rin', 'din', 'isang', 'nang', 'kapag', 'pag', 'dahil', 'upang', 'pero', 'ngunit',
        'subalit', 'kaya', 'saan', 'sino', 'kailan', 'alin', 'ilan', 'ilang', 'gaano', 'mayroon', 'meron', 'dito', 'doon', 'diyan', 'iyong', 'yung', 'lahat',
        'bawat', 'ibang', 'tungkol', 'gaya', 'tulad', 'habang', 'muna', 'nga', 'pala', 'kasi', 'ating', 'aming', 'kanilang', 'kaniyang',
        'kanyang', 'inyong', 'iyo', 'ikaw', 'ako', 'akin', 'atin', 'amin', 'nasa', 'mula', 'hanggang', 'bilang', 'sapagkat', 'kahit',
        'maging', 'pagkatapos', 'ni', 'si', 'sina', 'kay', 'kina', 'wala', 'walang', 'saka', 'tapos', 'mong', 'kong', 'nating', 'nilang',
        'niyang', 'siyang', 'pang', 'pa', 'daw', 'raw', 'sana'];
    private const ENGLISH = ['the', 'and', 'of', 'to', 'is', 'are', 'was', 'were', 'in', 'that', 'for', 'with', 'it', 'this', 'these',
        'those', 'on', 'as', 'be', 'by', 'from', 'or', 'an', 'a', 'what', 'how', 'why', 'when', 'which', 'who', 'your', 'you', 'can', 'do',
        'does', 'not', 'if', 'into', 'their', 'they', 'them', 'its', 'has', 'have', 'had', 'will', 'would', 'should', 'could', 'about',
        'then', 'than', 'so', 'but', 'there', 'here', 'also', 'each', 'both', 'because', 'between', 'only', 'more', 'most', 'other',
        'such', 'very', 'just', 'our', 'we', 'he', 'she', 'his', 'her', 'been', 'being', 'am', 'i', 'my', 'me', 'all', 'any', 'some', 'no'];

    /** @var array<string,string>|null word => language */
    private static ?array $words = null;

    /**
     * The language a reply should use ('en' or 'fil'), following the same rule the model is
     * given: an explicit request in the message wins, then a Filipino session, then the
     * language the message is written in.
     */
    public static function answerLanguage(string $sessionLanguage, string $message): string
    {
        if (preg_match('/\b(?:in|sa)\s+(?:filipino|tagalog)\b/iu', $message)) {
            return 'fil';
        }
        if (preg_match('/\b(?:in|sa)\s+(?:english|ingles)\b/iu', $message)) {
            return 'en';
        }
        if ($sessionLanguage === 'fil') {
            return 'fil';
        }
        return self::isFilipino($message) ? 'fil' : 'en';
    }

    public static function isFilipino(string $text): bool
    {
        $share = self::filipinoShare($text);
        return $share !== null && $share >= 0.5;
    }

    /** Filipino share of the function words in the prose, or null when fewer than $minWords were found. */
    public static function filipinoShare(string $text, int $minWords = 3): ?float
    {
        [$fil, $en] = self::counts($text);
        return $fil + $en >= $minWords ? (float) $fil / ($fil + $en) : null;
    }

    /** @return array{0:int,1:int} Filipino and English function words in the prose of $text */
    public static function counts(string $text): array
    {
        if (self::$words === null) {
            self::$words = array_fill_keys(self::ENGLISH, 'en') + array_fill_keys(self::FILIPINO, 'fil');
        }
        preg_match_all('/\p{L}+/u', mb_strtolower(self::prose($text)), $m);
        $fil = 0;
        $en = 0;
        foreach ($m[0] as $word) {
            $lang = self::$words[$word] ?? null;
            if ($lang === 'fil') {
                $fil++;
            } elseif ($lang === 'en') {
                $en++;
            }
        }
        return [$fil, $en];
    }

    /** The text without code, maths, block quotes, quotations, italic titles, links and citation markers. */
    public static function prose(string $text): string
    {
        $patterns = [
            '/```.*?```/s',
            '/`[^`\n]*`/',
            '/\$\$.*?\$\$|\\\\\[.*?\\\\\]|\\\\\(.*?\\\\\)|\$[^$\n]+\$/s',
            '/^\s*>.*$/m',
            '/“[^”]*”|"[^"\n]*"/u',
            '/(?<![*\w])\*(?!\*)[^*\n]+\*(?!\*)|(?<![_\w])_(?!_)[^_\n]+_(?![_\w])/u',
            '/https?:\/\/\S+/',
            '/\[\d{1,2}(?:\s*,\s*\d{1,2})*\]/',
        ];
        return (string) preg_replace($patterns, ' ', $text);
    }
}
