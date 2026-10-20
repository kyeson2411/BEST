<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Core\Validator;

final class ValidatorTest extends TestCase
{
    public function testRequiredTrimmedAndCast(): void
    {
        $v = Validator::make(['title' => '  Quiz 1  ', 'points' => '10', 'email' => 'Juan@Example.ORG'], [
            'title' => 'required|string|max:20',
            'points' => 'required|int|min:1|max:100',
            'email' => 'required|email',
        ]);
        self::assertFalse($v->fails());
        self::assertSame(['title' => 'Quiz 1', 'points' => 10, 'email' => 'juan@example.org'], $v->validated());
    }

    public function testRejectsArraysWhereTextIsExpected(): void
    {
        $v = Validator::make(['title' => ['x']], ['title' => 'required|string']);
        self::assertTrue($v->fails());
    }

    public function testNumericBoundsAndAllowedValues(): void
    {
        $v = Validator::make(['n' => '101', 'mode' => 'admin'], ['n' => 'int|max:100', 'mode' => 'in:student,teacher']);
        self::assertArrayHasKey('n', $v->errors());
        self::assertArrayHasKey('mode', $v->errors());
    }

    public function testUrlMustBeHttp(): void
    {
        self::assertTrue(Validator::make(['u' => 'javascript:alert(1)'], ['u' => 'url'])->fails());
        self::assertFalse(Validator::make(['u' => 'https://www.desmos.com/calculator'], ['u' => 'url'])->fails());
    }

    public function testBooleansDefaultToFalseWhenAbsent(): void
    {
        self::assertSame(['on' => false], Validator::make([], ['on' => 'bool'])->validated());
    }
}
