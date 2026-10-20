<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Services\Tokens;
use Sibol\Services\Usernames;

final class UsernamesAndCodesTest extends TestCase
{
    public function testUsernamesAreMadeFromTheNameInPlainLetters(): void
    {
        self::assertSame('andrea.cruz', Usernames::base('Andrea', 'Cruz'));
        self::assertSame('sofia.delacruz', Usernames::base('Sofia', 'Dela Cruz'));
        self::assertSame('juan.delacruz', Usernames::base('Juan Carlos', 'dela Cruz'));
        self::assertSame('materesa.villanueva', Usernames::base('Ma. Teresa', 'Villanueva'), 'a short first word joins the next');
        self::assertSame('nino.pena', Usernames::base('Niño', 'Peña'), 'accents and ñ fold to plain letters');
        self::assertSame('ana.obrien', Usernames::base('Ana', "O'Brien"));
        self::assertSame('userli', Usernames::base('', 'Li'), 'too short gets a prefix');
        self::assertTrue(Usernames::valid(Usernames::base('Juan Carlos', 'dela Cruz')));
        self::assertLessThanOrEqual(32, strlen(Usernames::base(str_repeat('Maximiliano ', 3), str_repeat('Villafuerte', 4))));
    }

    public function testUsernameRules(): void
    {
        foreach (['andrea.cruz', 'abc', 'j.o-2', 'juan_dc', 'andrea.cruz2'] as $ok) {
            self::assertTrue(Usernames::valid($ok), $ok);
        }
        foreach (['ab', '.andrea', 'andrea.', 'Andrea', 'and rea', 'a@b.c', str_repeat('a', 41), 'ñino'] as $bad) {
            self::assertFalse(Usernames::valid($bad), $bad);
        }
        self::assertSame('andrea.cruz', Usernames::clean('  Andrea.Cruz '));
    }

    public function testSlipCodesAreTypeableAndNormalised(): void
    {
        self::assertTrue(Tokens::isCode('ABCD-EFGH-JKMN'));
        self::assertTrue(Tokens::isCode('abcd efgh jkmn'), 'small letters and spaces are fine');
        self::assertSame('ABCDEFGHJKMN', Tokens::normalise(' abcd-efgh-jkmn '));
        self::assertSame('ABCD-EFGH-JKMN', Tokens::displayCode('abcdefghjkmn'));
        self::assertFalse(Tokens::isCode('ABCD-EFGH-JKM0'), 'zero is not in the alphabet');
        self::assertFalse(Tokens::isCode('ABCD-EFGH-JKMNP'), 'thirteen characters');
        // A long link token is case-sensitive and never rewritten.
        $long = 'aB3-dE5_fG7hJ9kL2mN4pQ6rS8tU0vW1xY2zA3bC4dE5';
        self::assertSame($long, Tokens::normalise($long));
        self::assertFalse(Tokens::isCode($long));
    }

    public function testPasswordsMayNotContainTheUsername(): void
    {
        self::assertNotNull(Tokens::passwordProblem('mango andrea.cruz tree', null, 'Andrea', 'andrea.cruz'));
        self::assertNull(Tokens::passwordProblem('mango tree by the river', null, 'Bea', 'bea.ramos'));
        self::assertNull(Tokens::passwordProblem('mango tree by the river', null, 'Bea', null), 'no email and no username');
    }
}
