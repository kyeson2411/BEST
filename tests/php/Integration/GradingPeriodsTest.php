<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Db;
use Sibol\Services\Grades;
use Sibol\Services\GradingPeriods;
use Sibol\Services\Settings;
use Sibol\Services\Transmutation;

/** Item 7: grading periods, weights per subject group, the transmutation table and final grades. */
final class GradingPeriodsTest extends IntegrationTestCase
{
    public function testThe2015TableReadsAsPublished(): void
    {
        $rows = Transmutation::deped2015();
        self::assertCount(41, $rows);
        self::assertSame([], Transmutation::problems($rows));
        $cases = [100.0 => 100.0, 99.99 => 99.0, 98.40 => 99.0, 98.39 => 98.0, 84.0 => 90.0, 83.99 => 89.0, 61.6 => 76.0, 61.59 => 75.0,
            60.0 => 75.0, 59.99 => 74.0, 56.0 => 74.0, 55.99 => 73.0, 4.0 => 61.0, 3.99 => 60.0, 0.0 => 60.0];
        foreach ($cases as $initial => $grade) {
            self::assertSame($grade, Transmutation::apply((float) $initial, $rows), "initial grade $initial");
        }
        self::assertSame(75.0, Transmutation::apply(61.594, $rows), 'initial grades are rounded to two decimals first');
        self::assertSame(76.0, Transmutation::apply(61.596, $rows));
    }

    public function testTablesCanBeTypedOrPastedAndAreChecked(): void
    {
        $rows = Transmutation::parse("60.00 – 61.59  75\n61.60\t76\n\nnotes without numbers\n0,00 60");
        self::assertSame([['min' => 61.6, 'grade' => 76.0], ['min' => 60.0, 'grade' => 75.0], ['min' => 0.0, 'grade' => 60.0]], $rows);
        self::assertSame([], Transmutation::problems($rows));
        self::assertNotSame([], Transmutation::problems(Transmutation::parse("61.60 76\n60 75")), 'must start at 0');
        self::assertNotSame([], Transmutation::problems(Transmutation::parse("61.60 74\n60 75\n0 60")), 'a higher initial grade cannot give less');
        self::assertNotSame([], Transmutation::problems(Transmutation::parse("60 75\n60 76\n0 60")), 'each initial grade once');
        self::assertSame($rows, Transmutation::parse(Transmutation::format($rows)), 'what the form shows reads back the same');

        $grading = ['transmutation' => ['enabled' => true, 'confirmed' => false, 'rows' => $rows]];
        self::assertNull(Transmutation::active($grading), 'not used until someone has checked it');
        $grading['transmutation']['confirmed'] = true;
        self::assertSame($rows, Transmutation::active($grading));
        $grading['transmutation']['enabled'] = false;
        self::assertNull(Transmutation::active($grading), 'checked but switched off');
    }

    public function testWorkCountsInThePeriodItsDueDateFallsIn(): void
    {
        $zone = new DateTimeZone('Asia/Manila');
        $periods = [
            ['id' => 1, 'name' => 'Quarter 1', 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-31'],
            ['id' => 2, 'name' => 'Quarter 2', 'starts_on' => '2026-09-01', 'ends_on' => '2026-11-07'],
        ];
        self::assertSame(1, GradingPeriods::effective(['due_at' => '2026-08-31 15:30:00'], $periods, $zone), '11:30 pm on 31 August in Manila');
        self::assertSame(2, GradingPeriods::effective(['due_at' => '2026-08-31 16:30:00'], $periods, $zone), '12:30 am on 1 September in Manila');
        self::assertSame(1, GradingPeriods::effective(['due_at' => '2026-10-01 01:00:00', 'grading_period_id' => 1], $periods, $zone), 'the teacher\'s choice wins');
        self::assertSame(2, GradingPeriods::effective(['due_at' => '2026-10-01 01:00:00', 'grading_period_id' => 99], $periods, $zone), 'a period from another year is ignored');
        self::assertNull(GradingPeriods::effective(['due_at' => '2026-12-01 01:00:00'], $periods, $zone), 'due outside every period');
        self::assertNull(GradingPeriods::effective(['due_at' => null], $periods, $zone));

        $at = static fn (string $utc) => new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        self::assertSame(2, GradingPeriods::current($periods, $zone, $at('2026-10-20 03:00:00')));
        self::assertSame(2, GradingPeriods::current($periods, $zone, $at('2026-12-20 03:00:00')), 'after the last period: the last one');
        self::assertSame(1, GradingPeriods::current($periods, $zone, $at('2026-05-01 03:00:00')), 'before the first: the first');
    }

    public function testPeriodsMustFitTheYearWithoutOverlapping(): void
    {
        $year = ['name' => 'School year 2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-09'];
        $others = [['name' => 'Quarter 1', 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-31']];
        self::assertSame([], GradingPeriods::problems($year, '2026-09-01', '2026-11-07', $others));
        self::assertCount(1, GradingPeriods::problems($year, '2026-08-31', '2026-11-07', $others), 'overlaps Quarter 1 by a day');
        self::assertCount(1, GradingPeriods::problems($year, '2027-03-01', '2027-04-30', $others), 'ends after the school year');
        self::assertNotSame([], GradingPeriods::problems($year, '2026-11-07', '2026-09-01', $others), 'ends before it starts');
    }

    public function testSplittingAYearMakesPeriodsWithoutGapsOrOverlaps(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $org = Db::insert('organizations', ['name' => 'Split Test School', 'slug' => 'split-test', 'timezone' => 'Asia/Manila', 'is_demo' => 1]);
            $id = Db::insert('academic_periods', ['organization_id' => $org, 'name' => 'School year 2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-09', 'is_current' => 1]);
            $year = Db::one('SELECT * FROM academic_periods WHERE id = :id', ['id' => $id]);
            self::assertSame(4, GradingPeriods::split($year, 4, 'Quarter'));
            self::assertSame(0, GradingPeriods::split($year, 3, 'Term'), 'only a year without periods is split');
            $periods = GradingPeriods::forAcademicPeriod($id);
            self::assertSame(['Quarter 1', 'Quarter 2', 'Quarter 3', 'Quarter 4'], array_column($periods, 'name'));
            self::assertSame('2026-06-15', $periods[0]['starts_on']);
            self::assertSame('2027-04-09', $periods[3]['ends_on']);
            for ($i = 1; $i < 4; $i++) {
                $next = (new DateTimeImmutable($periods[$i - 1]['ends_on']))->modify('+1 day')->format('Y-m-d');
                self::assertSame($next, $periods[$i]['starts_on'], 'each period starts the day after the last one ends');
            }
        } finally {
            $pdo->rollBack();
        }
    }

    public function testPeriodGradesUseTheGroupWeightsAndTheCheckedTable(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            [$org, $class, $student, $q1, $q2] = $this->school('math_science');
            $preset = Settings::gradingPresets()['deped2015']['value'];
            $preset['transmutation']['enabled'] = true;
            $preset['transmutation']['confirmed'] = true;
            Settings::override($org, 'grading', $preset);

            $book = Grades::classBook($class, $org);
            $p1 = $book['by_period'][$student][$q1];
            $p2 = $book['by_period'][$student][$q2];
            // Quarter 1: written 18/20 = 90%, performance (40 + 10)/(50 + 10) = 83.33% (the second one was placed in
            // Quarter 1 by hand), assessment 21/30 = 70%; Mathematics and Science weights 40/40/20.
            self::assertSame(83.33, $p1['initial']);
            self::assertSame(89.0, $p1['grade'], '82.40–83.99 transmutes to 89');
            self::assertSame('Very satisfactory', $p1['descriptor']);
            // Quarter 2: only written work so far, 5/10.
            self::assertSame(50.0, $p2['initial']);
            self::assertSame(72.0, $p2['grade'], '48.00–51.99 transmutes to 72');
            self::assertSame(['grade' => 81.0, 'periods' => 2, 'complete' => true, 'descriptor' => 'Satisfactory'], $book['final'][$student], '(89 + 72) / 2 = 80.5, a whole number');
            self::assertTrue($book['transmuted']);
            self::assertSame('Mathematics and Science', $book['grading']['group']);

            // The default weights (30/50/20) when the subject is in no group.
            Db::exec("UPDATE subjects SET grading_group = NULL WHERE organization_id = :o", ['o' => $org]);
            self::assertSame(82.67, Grades::classBook($class, $org)['by_period'][$student][$q1]['initial']);

            // Switched off: period grades are the percentages, and the final grade keeps one decimal.
            $preset['transmutation']['enabled'] = false;
            Settings::override($org, 'grading', $preset);
            $book = Grades::classBook($class, $org);
            self::assertFalse($book['transmuted']);
            self::assertSame(82.7, $book['by_period'][$student][$q1]['grade']);
            self::assertSame(66.4, $book['final'][$student]['grade'], '(82.7 + 50) / 2 = 66.35');

            // Without grading periods everything works as before: one overall grade from all work.
            Db::exec('DELETE FROM grading_periods WHERE organization_id = :o', ['o' => $org]);
            $book = Grades::classBook($class, $org);
            self::assertSame([], $book['periods']);
            self::assertSame([], $book['final']);
            self::assertSame(78.7, $book['overall'][$student]['percent'], 'written 23/30, performance 50/60, assessment 21/30 at 30/50/20');
        } finally {
            $pdo->rollBack();
            Settings::flush();
        }
    }

    /**
     * A school with one Science class, one student and five graded pieces of work: three due in Quarter 1,
     * one due in Quarter 2 but placed in Quarter 1 by its teacher, and one due in Quarter 2.
     * @return array{0:int,1:int,2:int,3:int,4:int} org, class, student, Quarter 1 id, Quarter 2 id
     */
    private function school(?string $group): array
    {
        $org = Db::insert('organizations', ['name' => 'Grading Test School', 'slug' => 'grading-test', 'timezone' => 'Asia/Manila', 'is_demo' => 1]);
        $year = Db::insert('academic_periods', ['organization_id' => $org, 'name' => 'School year 2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-09', 'is_current' => 1]);
        $q1 = Db::insert('grading_periods', ['organization_id' => $org, 'academic_period_id' => $year, 'name' => 'Quarter 1', 'position' => 1, 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-31']);
        $q2 = Db::insert('grading_periods', ['organization_id' => $org, 'academic_period_id' => $year, 'name' => 'Quarter 2', 'position' => 2, 'starts_on' => '2026-09-01', 'ends_on' => '2026-11-07']);
        $subject = Db::insert('subjects', ['organization_id' => $org, 'name' => 'Science', 'grading_group' => $group]);
        $class = Db::insert('classes', ['organization_id' => $org, 'subject_id' => $subject, 'academic_period_id' => $year, 'name' => 'Science 9 – Test', 'status' => 'active']);
        $teacher = Db::insert('users', ['organization_id' => $org, 'role' => 'teacher', 'given_name' => 'Grade', 'family_name' => 'Teacher', 'username' => 'grade.teacher', 'status' => 'active', 'is_demo' => 1]);
        $student = Db::insert('users', ['organization_id' => $org, 'role' => 'student', 'given_name' => 'Grade', 'family_name' => 'Student', 'username' => 'grade.student', 'status' => 'active', 'is_demo' => 1]);
        Db::insert('class_memberships', ['class_id' => $class, 'user_id' => $student, 'role' => 'student', 'status' => 'active']);
        $work = [
            ['written', 20, 18, '2026-07-10 01:00:00', null],
            ['performance', 50, 40, '2026-08-01 01:00:00', null],
            ['periodic', 30, 21, '2026-08-28 01:00:00', null],
            ['performance', 10, 10, '2026-09-10 01:00:00', $q1],
            ['written', 10, 5, '2026-09-20 01:00:00', null],
        ];
        foreach ($work as $i => [$category, $points, $score, $due, $period]) {
            $a = Db::insert('assignments', [
                'organization_id' => $org, 'class_id' => $class, 'kind' => 'assignment', 'title' => 'Task ' . ($i + 1), 'category' => $category,
                'grading_period_id' => $period, 'status' => 'published', 'due_at' => $due, 'points_possible' => $points, 'created_by' => $teacher,
            ]);
            Db::insert('submissions', [
                'organization_id' => $org, 'assignment_id' => $a, 'student_id' => $student, 'status' => 'submitted', 'submitted_at' => $due,
                'score' => $score, 'points_possible' => $points, 'grading_status' => 'graded', 'feedback_status' => 'released', 'released_at' => $due,
            ]);
        }
        return [$org, $class, $student, $q1, $q2];
    }
}
