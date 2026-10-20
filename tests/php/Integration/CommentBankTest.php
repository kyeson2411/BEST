<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Services\CommentBank;

/** Item 10: a teacher's own comment bank. */
final class CommentBankTest extends IntegrationTestCase
{
    public function testCommentsAreTiedToCriteriaByTitleAndOrderedByUse(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $ramon = $this->person('ramon.dizon');
            $general = CommentBank::add($ramon, '  Check your units in every answer.  ', '');
            $evidence = CommentBank::add($ramon, 'Quote the data that supports your claim.', 'use of  EVIDENCE');
            $other = CommentBank::add($ramon, 'Label both axes.', 'Graphs');

            $grading = CommentBank::forGrading((int) $ramon['id'], [['id' => 'c1', 'title' => 'Claim'], ['id' => 'c2', 'title' => 'Use of evidence']]);
            self::assertSame(3, $grading['count']);
            self::assertSame(['use of evidence'], array_keys($grading['criteria']), 'matched by title, ignoring case and spacing');
            self::assertSame('Use of evidence', $grading['criteria']['use of evidence']['title']);
            self::assertSame([$evidence], array_map('intval', array_column($grading['criteria']['use of evidence']['comments'], 'id')));
            self::assertSame([$other, $general], array_map('intval', array_column($grading['general'], 'id')), 'a criterion this rubric does not have is listed with the rest, newest first');
            self::assertSame('Check your units in every answer.', CommentBank::own($ramon, $general)['body'], 'trimmed');

            CommentBank::used($ramon, $other);
            CommentBank::used($ramon, $other);
            self::assertSame($other, (int) CommentBank::forTeacher((int) $ramon['id'])[0]['id'], 'the most used first');

            CommentBank::update($ramon, $other, 'Label both axes, with units.', '');
            self::assertNull(CommentBank::own($ramon, $other)['criterion']);
            CommentBank::delete($ramon, $general);
            self::assertCount(2, CommentBank::forTeacher((int) $ramon['id']));
        } finally {
            $pdo->rollBack();
        }
    }

    public function testOnlyTheirOwnerCanSeeOrChangeThem(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $ramon = $this->person('ramon.dizon');
            $joy = $this->person('joy.santiago');
            $id = CommentBank::add($ramon, 'Strong conclusion.', '');
            self::assertSame([], CommentBank::forTeacher((int) $joy['id']));
            foreach ([
                static fn () => CommentBank::own($joy, $id),
                static fn () => CommentBank::update($joy, $id, 'Changed', ''),
                static fn () => CommentBank::delete($joy, $id),
                static fn () => CommentBank::used($joy, $id),
            ] as $attempt) {
                try {
                    $attempt();
                    self::fail('another teacher reached the comment');
                } catch (HttpException $e) {
                    self::assertSame(404, $e->status);
                }
            }
            self::assertSame('Strong conclusion.', CommentBank::own($ramon, $id)['body']);
        } finally {
            $pdo->rollBack();
        }
    }

    public function testEmptyOrOverlongCommentsAreRefused(): void
    {
        self::assertArrayHasKey('body', CommentBank::problems('   ', ''));
        self::assertArrayHasKey('body', CommentBank::problems(str_repeat('a', CommentBank::MAX_BODY + 1), ''));
        self::assertArrayHasKey('criterion', CommentBank::problems('Fine', str_repeat('c', CommentBank::MAX_CRITERION + 1)));
        self::assertSame([], CommentBank::problems('Fine.', 'Use of evidence'));
    }

    public function testCriterionSuggestionsComeFromTheTeachersOwnRubrics(): void
    {
        $ramon = $this->person('ramon.dizon');
        $titles = CommentBank::criterionTitles($ramon);
        foreach ($titles as $title) {
            self::assertNotSame('', $title);
        }
        self::assertSame($titles, array_values(array_unique($titles)));
    }
}
