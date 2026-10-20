<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Core\Translator;
use Sibol\Services\TranslationAudit;

final class TranslationTest extends TestCase
{
    public function testFilipinoLinesKeepTheirPlaceholdersAndMatchRealStrings(): void
    {
        $source = TranslationAudit::sourceStrings();
        $lines = TranslationAudit::translations('fil');
        self::assertGreaterThan(400, count($lines));
        foreach ($lines as $english => $filipino) {
            self::assertArrayHasKey($english, $source, "lang/fil.php has a line for a string the code no longer uses: \"$english\"");
            self::assertSame(TranslationAudit::placeholders($english), TranslationAudit::placeholders($filipino), "Placeholders differ for \"$english\"");
            self::assertNotSame('', trim($filipino));
        }
    }

    public function testFallsBackToEnglishAndReplacesPlaceholders(): void
    {
        Translator::setLocale('fil');
        try {
            self::assertSame('Mag-sign in', t('Sign in'));
            self::assertSame('Lampas nang 3 araw', tp('Overdue by :count day', 'Overdue by :count days', 3));
            self::assertSame('A string nobody translated', t('A string nobody translated'));
        } finally {
            Translator::setLocale('en');
        }
    }
}
