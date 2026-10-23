<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Core\Translator;
use Sibol\Services\ScriptStrings;

/**
 * Scripts carry no English of their own: their text comes from ScriptStrings through data-i18n
 * attributes, so it is translated like the rest of the interface.
 */
final class ScriptStringsTest extends TestCase
{
    /** Each script and the ScriptStrings sets its page provides (the layout always provides common). */
    private const SCRIPTS = [
        'app.js' => ['common'],
        'builder.js' => ['common'],
        'companion.js' => ['companion', 'common'],
        'grading.js' => ['grading', 'common'],
        'materials.js' => ['material', 'common'],
        'offline.js' => ['offline', 'common'],
        'practice.js' => ['flashcards', 'common'],
        'quiz.js' => ['quiz', 'common'],
    ];

    /** Technical literals that look like words: the strict-mode directive, key names and header names. */
    private const ALLOWED = ['use strict', 'Accept', 'Enter', 'Escape'];

    public function testEveryKeyAScriptUsesIsProvided(): void
    {
        foreach ($this->scripts() as $name => $source) {
            self::assertArrayHasKey($name, self::SCRIPTS, "Add $name to ScriptStringsTest::SCRIPTS");
            $available = [];
            foreach (self::SCRIPTS[$name] as $set) {
                $available += ScriptStrings::$set();
            }
            foreach ($this->keysUsed($source) as $key) {
                self::assertArrayHasKey($key, $available, "$name uses S('$key'), which its page does not provide");
            }
        }
    }

    public function testEveryProvidedStringIsUsed(): void
    {
        $used = [];
        foreach ($this->scripts() as $name => $source) {
            foreach ($this->keysUsed($source) as $key) {
                foreach (self::SCRIPTS[$name] ?? [] as $set) {
                    $used[$set][$key] = true;
                }
            }
        }
        foreach (['common', 'quiz', 'companion', 'flashcards', 'material', 'grading', 'offline'] as $set) {
            foreach (array_keys(ScriptStrings::$set()) as $key) {
                self::assertArrayHasKey($key, $used[$set] ?? [], "ScriptStrings::$set() provides '$key', but no script uses it");
            }
        }
    }

    public function testStringsAreTextOrPluralPairs(): void
    {
        foreach (['common', 'quiz', 'companion', 'flashcards', 'material', 'grading', 'offline'] as $set) {
            foreach (ScriptStrings::$set() as $key => $value) {
                if (is_array($value)) {
                    self::assertCount(2, $value, "$set.$key must be a [singular, plural] pair");
                    self::assertStringContainsString(':count', $value[1], "$set.$key: a plural pair is chosen by :count");
                    $value = implode(' ', $value);
                }
                self::assertIsString($value);
                self::assertNotSame('', trim($value), "$set.$key is empty");
            }
        }
    }

    public function testScriptsCarryNoEnglishText(): void
    {
        foreach ($this->scripts() as $name => $source) {
            $text = array_values(array_filter($this->literals($source), [$this, 'looksLikeText']));
            self::assertSame([], $text, "$name has interface text that should come from ScriptStrings");
        }
    }

    public function testTextDetectorFlagsWordsButNotSelectors(): void
    {
        foreach (['Copied', 'Try again', 'Are you sure?', ' of ', 'saved ', ' answered', 'not saved yet', 'offline, will retry'] as $text) {
            self::assertTrue($this->looksLikeText($text), "should flag \"$text\"");
        }
        foreach (['btn btn--quiet', '[data-copy]', '.source-tag.is-active', 'a.cite', 'X-Requested-With', 'Content-Type', 'Accept',
            'application/x-ndjson', '(max-width: 759px)', 'input, textarea, select', ' – ', '<span class="spinner" aria-hidden="true"></span><span>'] as $text) {
            self::assertFalse($this->looksLikeText($text), "should not flag \"$text\"");
        }
    }

    public function testLiteralReaderSkipsCommentsAndRegularExpressions(): void
    {
        $js = "// 'not this'\nvar a = 'one'; /* \"nor this\" */ var b = s.replace(/[&<>\"']/g, \"two\"); var c = x / 2 / y; var d = `three`;";
        self::assertSame(['one', 'two', 'three'], $this->literals($js));
    }

    public function testFilipinoPagesGetFilipinoScriptText(): void
    {
        Translator::setLocale('fil');
        try {
            $common = ScriptStrings::common();
            self::assertSame('Isara ang mensahe', $common['dismiss']);
            self::assertSame(':count karakter pa ang natitira', $common['chars_left'][1], 'placeholders stay for the script to fill');
            self::assertSame('Sumasagot ang Katuwang.', ScriptStrings::companion()['answering']);
        } finally {
            Translator::setLocale('en');
        }
    }

    /** @return array<string,string> file name => source */
    private function scripts(): array
    {
        $out = [];
        foreach (glob(BASE_PATH . '/public/assets/js/*.js') ?: [] as $file) {
            $out[basename($file)] = (string) file_get_contents($file);
        }
        self::assertNotEmpty($out);
        return $out;
    }

    /** @return list<string> keys passed to S('…') */
    private function keysUsed(string $source): array
    {
        preg_match_all("/\\bS\\(\\s*'([a-z_]+)'/", $source, $m);
        return array_values(array_unique($m[1]));
    }

    /** True for a literal that reads as interface text rather than a selector, class list or name. */
    private function looksLikeText(string $s): bool
    {
        if (!preg_match('/[A-Za-z]/', $s) || in_array($s, self::ALLOWED, true)) {
            return false;
        }
        // Pieces of a sentence joined with other values: ' of ', 'saved '.
        if (preg_match('/^\s|\s$/', $s)) {
            return true;
        }
        // A capitalised word: 'Copied', 'Try again'.
        if (preg_match('/^[A-Z][a-z\']+(?:[\s,.!?…:;]|$)/u', $s)) {
            return true;
        }
        // Plain words in a row, outside selectors and class lists: 'not saved yet'.
        return preg_match('/\b[a-z]{2,} [a-z]{2,}\b/', $s) === 1 && preg_match('/--|\[|#|=|\.[a-z]/i', $s) !== 1;
    }

    /** @return list<string> string literals, skipping comments and regular expression literals */
    private function literals(string $js): array
    {
        $out = [];
        $len = strlen($js);
        $prev = '';
        for ($i = 0; $i < $len; $i++) {
            $c = $js[$i];
            $next = $js[$i + 1] ?? '';
            if ($c === '/' && $next === '/') {
                $end = strpos($js, "\n", $i);
                $i = $end === false ? $len : $end;
                continue;
            }
            if ($c === '/' && $next === '*') {
                $end = strpos($js, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
                continue;
            }
            if ($c === '/' && ($prev === '' || strpos('(,=:[!&|?{};+', $prev) !== false)) {
                $inClass = false;
                for ($i++; $i < $len; $i++) {
                    if ($js[$i] === '\\') {
                        $i++;
                    } elseif ($js[$i] === '[') {
                        $inClass = true;
                    } elseif ($js[$i] === ']') {
                        $inClass = false;
                    } elseif ($js[$i] === '/' && !$inClass) {
                        break;
                    }
                }
                $prev = '/';
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $text = '';
                for ($i++; $i < $len && $js[$i] !== $c; $i++) {
                    if ($js[$i] === '\\') {
                        $i++;
                        $text .= $js[$i] ?? '';
                        continue;
                    }
                    $text .= $js[$i];
                }
                $out[] = $text;
                $prev = $c;
                continue;
            }
            if (!ctype_space($c)) {
                $prev = $c;
            }
        }
        return $out;
    }
}
