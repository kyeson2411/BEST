<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Core\Markdown;

/** The renderer receives AI output and teacher text, so it must never pass HTML through. */
final class MarkdownTest extends TestCase
{
    public function testEscapesHtmlAndScript(): void
    {
        $html = Markdown::render("Hello <script>alert(1)</script> <img src=x onerror=alert(1)>");
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testOnlyHttpLinksBecomeAnchors(): void
    {
        $html = Markdown::render('[safe](https://example.org/a) and [bad](javascript:alert(1))');
        self::assertStringContainsString('<a href="https://example.org/a" rel="noopener noreferrer nofollow" target="_blank">safe</a>', $html);
        self::assertStringNotContainsString('href="javascript', $html);
    }

    public function testValidCitationsBecomeLinksAndUnknownOnesAreDropped(): void
    {
        $html = Markdown::render('Alveoli are thin [1]. Invented claim [7].', ['citations' => [1 => 'm5']]);
        self::assertStringContainsString('data-cite="1"', $html);
        self::assertStringNotContainsString('data-cite="7"', $html);
        self::assertStringNotContainsString('[7]', $html);
    }

    public function testCodeBlocksAreEscapedAndNotFormatted(): void
    {
        $html = Markdown::render("```\n<b>**not bold**</b>\n```");
        self::assertStringContainsString('&lt;b&gt;**not bold**&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<strong>', $html);
    }

    public function testPlaceholderControlCharactersInInputCannotInjectBlocks(): void
    {
        $html = Markdown::render("a \x1A0\x1A b \x1B0\x1B c");
        self::assertStringNotContainsString("\x1A", $html);
        self::assertStringNotContainsString("\x1B", $html);
    }

    public function testLineBreaksInTableCellsAreKeptButNoOtherTag(): void
    {
        // As the live model wrote reteaching ideas on 2026-10-25: one cell, several ideas split by <br>.
        $html = Markdown::render("| Topic | Ideas |\n| --- | --- |\n| Breathing | • Model lungs<br>• Concept map<br/>• Quiz <b>now</b> |");
        self::assertStringContainsString('<td>• Model lungs<br>• Concept map<br>• Quiz &lt;b&gt;now&lt;/b&gt;</td>', $html);
        self::assertSame(1, substr_count(Markdown::render('Text <br> outside a table'), '&lt;br&gt;'));
    }

    public function testPlainCutsAtWordBoundary(): void
    {
        $plain = Markdown::plain('How do the respiratory and circulatory systems work together when I exercise?', 70);
        self::assertSame('How do the respiratory and circulatory systems work together when I…', $plain);
        self::assertSame('Short bold title', Markdown::plain('Short **bold** title'));
    }
}
