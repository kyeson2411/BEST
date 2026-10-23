<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Core\Markdown;
use Sibol\Services\Ai\Generators;

/**
 * Reading the model's JSON answers (lesson drafts, quiz drafts, practice sets). Groq's gpt-oss sometimes
 * escapes them twice, so "\n" or a stray "\" would reach students; those are mended, while correct
 * answers, LaTeX and code are left as written.
 */
final class AiJsonTest extends TestCase
{
    public function testTwiceEscapedLineBreaksBecomeLineBreaks(): void
    {
        // As the live model wrote a lesson draft on 2026-10-21: line breaks escaped twice, maths escaped once.
        $json = Generators::parseJson(<<<'JSON'
            {"objectives": "1. **Explain** where it happens.\\n2. **Write** the equation.\\n3. **Describe** chloroplasts. [1][4]",
             "explanation": "The balanced equation is:\\n\\n\\[6\\,\\text{CO}_2 \\xrightarrow{\\text{light}} 6\\,\\text{O}_2\\]\\n\\nIt happens in **chloroplasts**."}
            JSON);

        self::assertSame("1. **Explain** where it happens.\n2. **Write** the equation.\n3. **Describe** chloroplasts. [1][4]", $json['objectives']);
        self::assertSame("The balanced equation is:\n\n" . '\[6\,\text{CO}_2 \xrightarrow{\text{light}} 6\,\text{O}_2\]' . "\n\nIt happens in **chloroplasts**.", $json['explanation']);
        $html = Markdown::render($json['objectives']);
        self::assertSame(3, substr_count($html, '<li>'));
        self::assertStringNotContainsString('\n', $html);
    }

    public function testStrayBackslashBeforeALineBreakIsRemoved(): void
    {
        $json = Generators::parseJson(<<<'JSON'
            {"activities": "- **Diagram (10 min):** Label the leaf.\\\n- **Pair (8 min):** Discuss.", "checks": "**Quick questions:**\\\n  1. What are the reactants?"}
            JSON);

        self::assertSame("- **Diagram (10 min):** Label the leaf.\n- **Pair (8 min):** Discuss.", $json['activities']);
        self::assertSame("**Quick questions:**\n  1. What are the reactants?", $json['checks']);
    }

    public function testCorrectlyEscapedAnswersAreLeftAsWritten(): void
    {
        $raw = <<<'JSON'
            {"explanation": "### The equation\n\\[6\\text{CO}_2 \\rightarrow 6\\text{O}_2\\]\n\n- Is \\(a \\neq b\\)? See `printf(\"Hi\\n\")`.", "note": "Save it in C:\\notes\\week1 first.\nThen open it."}
            JSON;

        self::assertSame(json_decode($raw, true), Generators::parseJson($raw));
    }

    public function testLatexThatStartsWithNIsKept(): void
    {
        $json = Generators::parseJson(<<<'JSON'
            {"checks": "1. Is \\(x \\neq \\nu\\)?\\n2. Show \\(\\nabla f\\) and that x \\neq y.\\n3. Done."}
            JSON);

        self::assertSame('1. Is \(x \neq \nu\)?' . "\n" . '2. Show \(\nabla f\) and that x \neq y.' . "\n" . '3. Done.', $json['checks']);
    }

    public function testCodeIsKeptAsWritten(): void
    {
        $json = Generators::parseJson(<<<'JSON'
            {"activities": "- Type `print(\"Hi\\n\")` and run it.\\n- Change the text."}
            JSON);

        self::assertSame('- Type `print("Hi\n")` and run it.' . "\n" . '- Change the text.', $json['activities']);
    }

    public function testEveryStringInTheAnswerIsMended(): void
    {
        $json = Generators::parseJson(<<<'JSON'
            {"items": [{"front": "Photosynthesis", "back": "Light energy makes glucose.\\nOxygen is released.", "answer": 0, "explanation": "Ask: \\\"Why do leaves need light?\\\""}]}
            JSON);

        self::assertSame("Light energy makes glucose.\nOxygen is released.", $json['items'][0]['back']);
        self::assertSame('Ask: "Why do leaves need light?"', $json['items'][0]['explanation']);
        self::assertSame(0, $json['items'][0]['answer']);
    }
}
