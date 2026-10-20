<?php

declare(strict_types=1);

namespace Sibol\Core;

/**
 * Safe Markdown subset for AI answers, lesson content and announcements.
 *
 * Everything is HTML-escaped first; only a whitelist of structures is then re-created:
 * paragraphs, headings, lists (one level of nesting), block quotes, code, simple tables,
 * bold/italic, http(s) links, KaTeX maths spans, and citation markers that point at
 * sources we actually supplied. Raw HTML from the input is never emitted.
 */
final class Markdown
{
    /**
     * @param array{citations?:array<int,string>,heading_base?:int} $opts
     *   citations: map of valid source number => anchor prefix; markers for any other number are removed.
     */
    public static function render(?string $text, array $opts = []): string
    {
        $text = str_replace(["\r\n", "\r", "\x1A", "\x1B"], ["\n", "\n", '', ''], (string) $text);
        if (trim($text) === '') {
            return '';
        }
        $slots = [];
        $hold = static function (string $html) use (&$slots): string {
            $slots[] = $html;
            return "\x1A" . (count($slots) - 1) . "\x1A";
        };

        // Fenced code blocks are block-level holds (marker \x1B) so they never sit inside a <p>.
        $text = (string) preg_replace_callback('/^```[ \t]*[\w+#-]*[ \t]*\n(.*?)\n```[ \t]*$/ms', static function (array $m) use (&$slots): string {
            $slots[] = '<pre class="md-code"><code>' . e($m[1]) . '</code></pre>';
            return "\n\x1B" . (count($slots) - 1) . "\x1B\n";
        }, $text);

        // Display maths: $$...$$ and \[...\].
        $text = (string) preg_replace_callback('/\$\$(.+?)\$\$|\\\\\[(.+?)\\\\\]/s', static function (array $m) use ($hold): string {
            $tex = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
            return $hold('<span class="math math--display">\\[' . e(trim($tex)) . '\\]</span>');
        }, $text);

        // Inline code.
        $text = (string) preg_replace_callback('/`([^`\n]+)`/', static function (array $m) use ($hold): string {
            return $hold('<code>' . e($m[1]) . '</code>');
        }, $text);

        // Inline maths: \( ... \) and $...$ (only when it looks like maths, not money).
        $text = (string) preg_replace_callback('/\\\\\((.+?)\\\\\)/', static function (array $m) use ($hold): string {
            return $hold('<span class="math">\\(' . e($m[1]) . '\\)</span>');
        }, $text);
        $text = (string) preg_replace_callback('/(?<![\\\\\w$])\$(?=\S)([^$\n]{1,200}?)(?<=\S)\$(?![\w$])/', static function (array $m) use ($hold): string {
            $inner = $m[1];
            $mathy = preg_match('/[\\\\^_=+*\/<>{}]|[a-zA-Z]\d|\d[a-zA-Z]|^[a-zA-Z]$/', $inner) === 1;
            if (!$mathy || preg_match('/^\d+([.,]\d+)*$/', $inner)) {
                return $m[0];
            }
            return $hold('<span class="math">\\(' . e($inner) . '\\)</span>');
        }, $text);

        $html = self::blocks(e($text), $opts);

        return (string) preg_replace_callback('/[\x1A\x1B](\d+)[\x1A\x1B]/', static function (array $m) use ($slots): string {
            return $slots[(int) $m[1]] ?? '';
        }, $html);
    }

    /** Plain-text excerpt for previews and titles. */
    public static function plain(?string $text, int $limit = 160): string
    {
        $text = (string) $text;
        $text = (string) preg_replace('/```.*?```/s', ' ', $text);
        $text = (string) preg_replace('/\[(\d{1,2}(?:\s*,\s*\d{1,2})*)\]/', '', $text);
        $text = (string) preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = str_replace(['**', '__', '`', '#', '>', '$$'], '', $text);
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        // Cut at a word boundary when one is reasonably close, so titles never end mid-word.
        $cut = mb_substr($text, 0, $limit - 1);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $limit * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }
        return rtrim($cut, " ,;:.-") . '…';
    }

    private static function blocks(string $text, array $opts): string
    {
        $headingBase = max(2, min(5, (int) ($opts['heading_base'] ?? 3)));
        $lines = explode("\n", $text);
        $out = [];
        $para = [];
        $quote = [];
        $table = [];
        $lists = []; // stack of ['type' => 'ul'|'ol', 'indent' => int]

        $flushPara = static function () use (&$para, &$out, $opts): void {
            if ($para) {
                $out[] = '<p>' . self::inline(implode("<br>\n", $para), $opts) . '</p>';
                $para = [];
            }
        };
        $closeLists = static function (int $toIndent = -1) use (&$lists, &$out): void {
            while ($lists && end($lists)['indent'] > $toIndent) {
                $list = array_pop($lists);
                $out[] = '</li></' . $list['type'] . '>';
            }
        };
        $flushQuote = static function () use (&$quote, &$out, $opts): void {
            if ($quote) {
                $out[] = '<blockquote><p>' . self::inline(implode("<br>\n", $quote), $opts) . '</p></blockquote>';
                $quote = [];
            }
        };
        $flushTable = static function () use (&$table, &$out, &$para, $opts): void {
            if (!$table) {
                return;
            }
            $isSep = static fn (string $row): bool => (bool) preg_match('/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?$/', $row);
            if (count($table) >= 2 && $isSep($table[1])) {
                $cells = static fn (string $row): array => array_map('trim', explode('|', trim(trim($row), '|')));
                $html = '<div class="table-frame"><table class="md-table"><thead><tr>';
                foreach ($cells($table[0]) as $cell) {
                    $html .= '<th scope="col">' . self::inline($cell, $opts) . '</th>';
                }
                $html .= '</tr></thead><tbody>';
                foreach (array_slice($table, 2) as $row) {
                    $html .= '<tr>';
                    foreach ($cells($row) as $cell) {
                        $html .= '<td>' . self::inline($cell, $opts) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $out[] = $html . '</tbody></table></div>';
            } else {
                foreach ($table as $row) {
                    $para[] = $row;
                }
            }
            $table = [];
        };
        $flushAll = static function () use ($flushPara, $closeLists, $flushQuote, $flushTable): void {
            $flushTable();
            $flushPara();
            $closeLists();
            $flushQuote();
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $flushAll();
                continue;
            }
            if (preg_match('/^\x1B\d+\x1B$/', $trimmed)) {
                // A fenced code block stands on its own.
                $flushAll();
                $out[] = $trimmed;
                continue;
            }
            if (preg_match('/^(\s*)([-*+]|\d{1,3}[.)])\s+(.*)$/', $line, $m)) {
                $flushTable();
                $flushPara();
                $flushQuote();
                $indent = strlen(str_replace("\t", '  ', $m[1]));
                $type = ctype_digit(rtrim($m[2], '.)')) ? 'ol' : 'ul';
                $content = self::inline($m[3], $opts);
                $startAttr = ($type === 'ol' && (int) $m[2] > 1) ? ' start="' . (int) $m[2] . '"' : '';
                if (!$lists) {
                    $lists[] = ['type' => $type, 'indent' => $indent];
                    $out[] = '<' . $type . $startAttr . '><li>' . $content;
                } elseif ($indent > end($lists)['indent'] + 1 && count($lists) < 3) {
                    $lists[] = ['type' => $type, 'indent' => $indent];
                    $out[] = '<' . $type . '><li>' . $content;
                } else {
                    $closeLists($indent);
                    if ($lists && end($lists)['type'] !== $type && end($lists)['indent'] === $indent) {
                        $closeLists($indent - 1);
                    }
                    if (!$lists) {
                        $lists[] = ['type' => $type, 'indent' => $indent];
                        $out[] = '<' . $type . $startAttr . '><li>' . $content;
                    } else {
                        $out[] = '</li><li>' . $content;
                    }
                }
                continue;
            }
            if ($lists && preg_match('/^\s{2,}\S/', $line)) {
                $out[] = '<br>' . self::inline($trimmed, $opts);
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $trimmed, $m)) {
                $flushAll();
                $level = min(6, $headingBase + strlen($m[1]) - 1);
                $out[] = '<h' . $level . ' class="md-h">' . self::inline($m[2], $opts) . '</h' . $level . '>';
                continue;
            }
            if (preg_match('/^&gt;\s?(.*)$/', $trimmed, $m)) {
                $flushTable();
                $flushPara();
                $closeLists();
                $quote[] = $m[1];
                continue;
            }
            if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trimmed)) {
                $flushAll();
                $out[] = '<hr>';
                continue;
            }
            if (str_starts_with($trimmed, '|') && substr_count($trimmed, '|') >= 2) {
                $flushPara();
                $closeLists();
                $flushQuote();
                $table[] = $trimmed;
                continue;
            }
            $flushTable();
            $closeLists();
            $flushQuote();
            $para[] = $trimmed;
        }
        $flushAll();
        return implode("\n", $out);
    }

    private static function inline(string $s, array $opts): string
    {
        // Links: [text](https://...) — text and URL are already escaped.
        $s = (string) preg_replace_callback('/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/', static function (array $m): string {
            return '<a href="' . $m[2] . '" rel="noopener noreferrer nofollow" target="_blank">' . $m[1] . '</a>';
        }, $s);
        $s = (string) preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $s);
        $s = (string) preg_replace('/(?<![\w])__(?=\S)(.+?)(?<=\S)__(?![\w])/s', '<strong>$1</strong>', $s);
        $s = (string) preg_replace('/(?<![*\w])\*(?=\S)(.+?)(?<=\S)\*(?![*\w])/s', '<em>$1</em>', $s);
        $s = (string) preg_replace('/(?<![_\w])_(?=\S)(.+?)(?<=\S)_(?![_\w])/s', '<em>$1</em>', $s);

        if (array_key_exists('citations', $opts)) {
            $valid = $opts['citations'];
            $s = (string) preg_replace_callback('/\s?\[(\d{1,2}(?:\s*,\s*\d{1,2})*)\]/', static function (array $m) use ($valid): string {
                $links = [];
                foreach (preg_split('/\s*,\s*/', $m[1]) ?: [] as $n) {
                    $n = (int) $n;
                    if (isset($valid[$n])) {
                        $links[] = '<a class="cite" href="#' . e($valid[$n]) . '-' . $n . '" data-cite="' . $n . '">'
                            . '<span class="sr-only">' . e(t('Source')) . ' </span>' . $n . '</a>';
                    }
                }
                return $links ? '<span class="cites">' . implode('', $links) . '</span>' : '';
            }, $s);
        }
        return $s;
    }
}
