<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Db;
use Sibol\Services\Nudges;

/** Item 11: who needs a nudge. */
final class NudgesTest extends IntegrationTestCase
{
    public function testStudentsWithTwoMissingOrLateItemsInTwoWeeksAreListedByName(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $s = $this->school();
            $list = Nudges::forClass($s['class'], new DateTimeImmutable('now'));
            self::assertSame(['Abad', 'Bautista', 'Estrada'], array_map(static fn ($r) => $r['student']['family_name'], $list), 'by name, never by how much is missing');

            [$ana, $ben, $eve] = $list;
            self::assertSame(['Due five days ago', 'Due three days ago'], $ana['missing'], 'work in class with no score yet is not missing');
            self::assertSame([], $ana['late']);
            self::assertSame(['Due five days ago'], $ben['missing']);
            self::assertSame(['Due three days ago'], $ben['late'], 'handed in late counts');
            self::assertSame(['In class four days ago', 'Due three days ago'], $eve['missing'], 'absent from work done in class counts as missing');
            foreach ($list as $row) {
                self::assertArrayNotHasKey('score', $row['student'], 'no scores or ranks');
            }
            // Cara: one missing and one excused. Dan: both extended to next week. Neither is listed; nor is work due
            // three weeks ago or next week.
        } finally {
            $pdo->rollBack();
        }
    }

    public function testANudgeIsANeutralReminderAtMostOnceADay(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $s = $this->school();
            $class = Db::one('SELECT * FROM classes WHERE id = :c', ['c' => $s['class']]);
            $ana = Db::one('SELECT * FROM users WHERE id = :u', ['u' => $s['ana']]);
            $now = new DateTimeImmutable('now');
            self::assertSame('sent', Nudges::send($class, $ana, $now));
            self::assertSame('already', Nudges::send($class, $ana, $now));
            $note = Db::one("SELECT * FROM notifications WHERE user_id = :u AND type = 'reminder' ORDER BY id DESC LIMIT 1", ['u' => $s['ana']]);
            self::assertSame('A reminder about Science 9 – Nudge', $note['title']);
            self::assertStringNotContainsString('%', (string) $note['body'], 'no scores in the reminder');
            self::assertNotNull(Nudges::forClass($s['class'], $now)[0]['nudged_on']);
            self::assertSame('sent', Nudges::send($class, $ana, $now->modify('+1 day')), 'the next day, another one is possible');

            $ben = Db::one('SELECT * FROM users WHERE id = :u', ['u' => $s['ben']]);
            Db::update('users', ['preferences' => json_encode(['muted_notifications' => ['reminder']])], ['id' => $s['ben']]);
            $ben['preferences'] = json_encode(['muted_notifications' => ['reminder']]);
            self::assertSame('muted', Nudges::send($class, $ben, $now), 'a student who turned reminders off is not sent one');
        } finally {
            $pdo->rollBack();
        }
    }

    /** @return array<string,int> */
    private function school(): array
    {
        $org = Db::insert('organizations', ['name' => 'Nudge Test School', 'slug' => 'nudge-test', 'timezone' => 'Asia/Manila', 'is_demo' => 1]);
        $subject = Db::insert('subjects', ['organization_id' => $org, 'name' => 'Science']);
        $class = Db::insert('classes', ['organization_id' => $org, 'subject_id' => $subject, 'name' => 'Science 9 – Nudge', 'status' => 'active']);
        $teacher = Db::insert('users', ['organization_id' => $org, 'role' => 'teacher', 'given_name' => 'Nora', 'family_name' => 'Teacher', 'username' => 'nora.teacher.nt', 'status' => 'active', 'is_demo' => 1]);
        $people = [];
        foreach (['Ana Abad', 'Ben Bautista', 'Cara Cruz', 'Dan Diaz', 'Eve Estrada'] as $name) {
            [$given, $family] = explode(' ', $name);
            $id = Db::insert('users', ['organization_id' => $org, 'role' => 'student', 'given_name' => $given, 'family_name' => $family,
                'username' => strtolower($given) . '.nt', 'status' => 'active', 'is_demo' => 1]);
            Db::insert('class_memberships', ['class_id' => $class, 'user_id' => $id, 'role' => 'student', 'status' => 'active']);
            $people[strtolower($given)] = $id;
        }
        $utc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $work = static function (string $title, string $kind, string $when) use ($org, $class, $teacher, $utc): int {
            return Db::insert('assignments', ['organization_id' => $org, 'class_id' => $class, 'kind' => $kind, 'title' => $title, 'status' => 'published',
                'due_at' => $utc->modify($when)->format('Y-m-d H:i:s'), 'points_possible' => 10, 'created_by' => $teacher]);
        };
        $three = $work('Due three days ago', 'assignment', '-3 days');
        $five = $work('Due five days ago', 'assignment', '-5 days');
        $paper = $work('In class four days ago', 'paper', '-4 days');
        $work('Due three weeks ago', 'assignment', '-21 days');
        $work('Due next week', 'assignment', '+7 days');
        $hand = static function (int $assignment, int $student, bool $late, string $source = 'online') use ($org, $utc): void {
            Db::insert('submissions', ['organization_id' => $org, 'assignment_id' => $assignment, 'student_id' => $student, 'status' => 'submitted', 'source' => $source,
                'submitted_at' => $utc->format('Y-m-d H:i:s'), 'is_late' => $late ? 1 : 0]);
        };
        $override = static function (int $assignment, int $student, array $row) use ($org, $teacher): void {
            Db::insert('assignment_overrides', ['organization_id' => $org, 'assignment_id' => $assignment, 'student_id' => $student, 'created_by' => $teacher] + $row);
        };
        // Ana: two missing; the paper task has no score yet.
        // Ben: one late, one missing.
        $hand($three, $people['ben'], true);
        // Cara: one missing, one excused, the paper task scored.
        $override($five, $people['cara'], ['excused' => 1]);
        $hand($paper, $people['cara'], false, 'paper');
        // Dan: both online tasks extended to next week; the paper task scored.
        $override($three, $people['dan'], ['due_at' => $utc->modify('+7 days')->format('Y-m-d H:i:s')]);
        $override($five, $people['dan'], ['due_at' => $utc->modify('+7 days')->format('Y-m-d H:i:s')]);
        $hand($paper, $people['dan'], false, 'paper');
        // Eve: one missing, the five-day one handed in on time, absent from the paper task.
        $hand($five, $people['eve'], false);
        $override($paper, $people['eve'], ['absent' => 1]);
        return ['org' => $org, 'class' => $class, 'ana' => $people['ana'], 'ben' => $people['ben']];
    }
}
