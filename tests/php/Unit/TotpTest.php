<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Services\Totp;

/** Codes for two-step sign-in, checked against the test values published in RFC 6238 and RFC 4648. */
final class TotpTest extends TestCase
{
    /** RFC 6238, appendix B (HMAC-SHA1, the secret "12345678901234567890", 8 digits). */
    public function testCodesMatchThePublishedTestValues(): void
    {
        $secret = '12345678901234567890';
        $expected = [59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'];
        foreach ($expected as $time => $code) {
            self::assertSame($code, Totp::code($secret, Totp::step($time), 8), "T = $time");
            self::assertSame(substr($code, 2), Totp::code($secret, Totp::step($time)), "6 digits at T = $time");
        }
    }

    public function testBase32MatchesThePublishedTestValuesWithoutPadding(): void
    {
        foreach (['' => '', 'f' => 'MY', 'fo' => 'MZXQ', 'foo' => 'MZXW6', 'foob' => 'MZXW6YQ', 'fooba' => 'MZXW6YTB', 'foobar' => 'MZXW6YTBOI'] as $in => $out) {
            self::assertSame($out, Totp::base32((string) $in));
        }
        self::assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', Totp::base32('12345678901234567890'));
    }

    public function testACodeWorksForOneStepEitherSideAndOnlyOnce(): void
    {
        $secret = '12345678901234567890';
        $now = 1111111111;
        $step = Totp::step($now);
        self::assertSame($step, Totp::match($secret, Totp::code($secret, $step), $now));
        self::assertSame($step - 1, Totp::match($secret, Totp::code($secret, $step - 1), $now), 'a phone a little behind');
        self::assertSame($step + 1, Totp::match($secret, ' ' . substr_replace(Totp::code($secret, $step + 1), ' ', 3, 0), $now), 'spaces are ignored');
        self::assertNull(Totp::match($secret, Totp::code($secret, $step - 2), $now), 'a minute old');
        self::assertNull(Totp::match($secret, Totp::code($secret, $step), $now, $step), 'already used to sign in');
        self::assertNull(Totp::match($secret, Totp::code($secret, $step - 1), $now, $step), 'older than the last one used');
        self::assertSame($step + 1, Totp::match($secret, Totp::code($secret, $step + 1), $now, $step));
        foreach (['', '12345', '1234567', 'abcdef', '12345a'] as $bad) {
            self::assertNull(Totp::match($secret, $bad, $now), var_export($bad, true));
        }
    }

    public function testTheAppAddressNamesTheAccountAndCarriesTheSecret(): void
    {
        $uri = Totp::uri('12345678901234567890', 'joy.santiago', 'Sibol B.E.S.T.');
        self::assertSame('otpauth://totp/Sibol%20B.E.S.T.:joy.santiago?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=Sibol%20B.E.S.T.&algorithm=SHA1&digits=6&period=30', $uri);
    }
}
