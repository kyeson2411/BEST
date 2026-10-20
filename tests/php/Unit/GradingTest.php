<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Services\Assessments;

/** Automatic marking for objective questions; open questions always go to the teacher. */
final class GradingTest extends TestCase
{
    public function testMultipleChoiceIsCaseInsensitiveOnTheLetter(): void
    {
        $q = ['qtype' => 'multiple_choice', 'points' => 2, 'answer_key' => ['correct' => 'B']];
        self::assertSame([true, 2.0, false], Assessments::autograde($q, 'b'));
        self::assertSame([false, 0.0, false], Assessments::autograde($q, 'C'));
        self::assertSame([false, 0.0, false], Assessments::autograde($q, null));
    }

    public function testTrueFalse(): void
    {
        $q = ['qtype' => 'true_false', 'points' => 1, 'answer_key' => '{"correct":false}'];
        self::assertSame([true, 1.0, false], Assessments::autograde($q, 'false'));
        self::assertSame([false, 0.0, false], Assessments::autograde($q, 'true'));
    }

    public function testShortAnswerNormalisesSpacingAndFinalPunctuation(): void
    {
        $q = ['qtype' => 'short_answer', 'points' => 1, 'answer_key' => ['accepted' => ['diaphragm'], 'case_sensitive' => false]];
        self::assertSame([true, 1.0, false], Assessments::autograde($q, '  Diaphragm. '));
        self::assertSame([false, 0.0, false], Assessments::autograde($q, 'The diaphragm'), 'Extra words count only when the teacher lists them');
    }

    public function testShortAnswerAcceptsListedAnswersOnly(): void
    {
        $q = ['qtype' => 'short_answer', 'points' => 1, 'answer_key' => ['accepted' => ['Diaphragm', 'the diaphragm'], 'case_sensitive' => false]];
        self::assertSame([true, 1.0, false], Assessments::autograde($q, 'the  DIAPHRAGM.'));
        self::assertSame([false, 0.0, false], Assessments::autograde($q, 'lungs'));
    }

    public function testOpenQuestionsNeedTheTeacher(): void
    {
        self::assertSame([null, null, true], Assessments::autograde(['qtype' => 'long_answer', 'points' => 5, 'answer_key' => null], 'My essay'));
    }

    public function testQuestionValidation(): void
    {
        [$row, $errors] = Assessments::normaliseQuestion(['qtype' => 'multiple_choice', 'prompt' => 'Pick one', 'points' => '2', 'options' => ['Yes', '', 'No'], 'correct' => 'b']);
        self::assertSame([], $errors);
        self::assertSame('{"correct":"B"}', $row['answer_key']);
        self::assertSame([['key' => 'A', 'text' => 'Yes'], ['key' => 'B', 'text' => 'No']], json_decode((string) $row['options'], true));

        [, $errors] = Assessments::normaliseQuestion(['qtype' => 'multiple_choice', 'prompt' => '', 'options' => ['Only one'], 'correct' => 'C']);
        self::assertArrayHasKey('prompt', $errors);
        self::assertArrayHasKey('options', $errors);
        self::assertArrayHasKey('correct', $errors);
    }
}
