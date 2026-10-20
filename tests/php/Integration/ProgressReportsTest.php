<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Policies\Access;
use Sibol\Services\GradingPeriods;
use Sibol\Services\ProgressReports;
use Sibol\Services\Settings;

/** Item 8: progress reports per grading period for a student and for their section's adviser. */
final class ProgressReportsTest extends IntegrationTestCase
{
    public function testAStudentsReportShowsReleasedWorkFromOnePeriod(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $s = $this->school();
            $student = $this->user($s['andrea']);
            $mine = [$s['english'], $s['science'], $s['journalism']];

            $r = ProgressReports::build($s['org'], [$student], $mine, (string) $s['q1']);
            self::assertSame($s['q1'], (int) $r['period']['id']);
            self::assertSame($s['year'], (int) $r['year']['id']);
            $rows = $this->rows($r, $s['andrea']);
            self::assertSame(['English 9 – Report', 'Journalism Club', 'Science 9 – Report'], array_keys($rows), 'by subject, every class the student takes');

            // Science, Quarter 1: 18/20 and 15/20 released; a graded score that is not released is left out.
            self::assertSame(82.5, $rows['Science 9 – Report']['grade']['grade']);
            self::assertSame(4, $rows['Science 9 – Report']['work']);
            self::assertSame(1, $rows['Science 9 – Report']['missing'], 'the task with nothing handed in');
            self::assertSame(1, $rows['Science 9 – Report']['late']);
            self::assertSame(70.0, $rows['English 9 – Report']['grade']['grade']);
            self::assertSame(0, $rows['English 9 – Report']['missing']);
            self::assertSame(['Clear and careful.', 'Late, but complete.'], array_column($r['reports'][$s['andrea']]['comments'], 'feedback'),
                'released comments from this period only; the unreleased one is left out');

            $r = ProgressReports::build($s['org'], [$student], $mine, (string) $s['q2']);
            $rows = $this->rows($r, $s['andrea']);
            self::assertSame(90.0, $rows['Science 9 – Report']['grade']['grade']);
            self::assertNull($rows['English 9 – Report']['grade']['grade'], 'no English work in Quarter 2');
            self::assertSame(0, $rows['English 9 – Report']['work']);
            self::assertSame(['Second quarter comment.'], array_column($r['reports'][$s['andrea']]['comments'], 'feedback'));

            // A period from the request that is not in this school year falls back to the current one.
            $r = ProgressReports::build($s['org'], [$student], $mine, '999999');
            $current = GradingPeriods::current(GradingPeriods::forAcademicPeriod($s['year']), GradingPeriods::zone($s['org']));
            self::assertSame($current, (int) $r['period']['id']);

            // Without grading periods the report covers the school year so far.
            Db::exec('DELETE FROM grading_periods WHERE organization_id = :o', ['o' => $s['org']]);
            $r = ProgressReports::build($s['org'], [$student], $mine, null);
            self::assertNull($r['period']);
            $rows = $this->rows($r, $s['andrea']);
            self::assertSame(84.0, $rows['Science 9 – Report']['grade']['grade'], '(18 + 15 + 9) / (20 + 20 + 10)');
            self::assertCount(3, $r['reports'][$s['andrea']]['comments']);
        } finally {
            $pdo->rollBack();
            Settings::flush();
        }
    }

    public function testAnAdviserSeesTheSectionsSubjectsForTheirStudentsOnly(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $s = $this->school();
            $section = ProgressReports::sectionClassIds($s['section']);
            sort($section);
            $expected = [$s['english'], $s['science']];
            sort($expected);
            self::assertSame($expected, $section, 'the club is not one of the section\'s classes');

            $r = ProgressReports::build($s['org'], [$this->user($s['andrea']), $this->user($s['ben'])], $section, (string) $s['q1']);
            self::assertSame(['English 9 – Report', 'Science 9 – Report'], array_keys($this->rows($r, $s['andrea'])));
            $ben = $this->rows($r, $s['ben']);
            self::assertSame(['English 9 – Report', 'Science 9 – Report'], array_keys($ben));
            self::assertNull($ben['Science 9 – Report']['grade']['grade']);
            self::assertSame(4, $ben['Science 9 – Report']['missing']);
            self::assertSame(1, $ben['English 9 – Report']['missing']);
            self::assertSame([], $r['reports'][$s['ben']]['comments']);

            $adviser = $this->user($s['adviser']);
            [, $found] = Access::advisedStudent($adviser, $s['section'], $s['andrea']);
            self::assertSame($s['andrea'], (int) $found['id']);
            $this->assertNotFound(fn () => Access::adviserSection($this->user($s['english_teacher']), $s['section']), 'a subject teacher is not the adviser');
            $this->assertNotFound(fn () => Access::advisedStudent($adviser, $s['section'], $s['clubOnly']), 'a student outside the section');
        } finally {
            $pdo->rollBack();
            Settings::flush();
        }
    }

    /** @return array<string,array<string,mixed>> rows of one student's report by class name */
    private function rows(array $built, int $studentId): array
    {
        $out = [];
        foreach ($built['reports'][$studentId]['rows'] as $row) {
            $out[$row['class']['name']] = $row;
        }
        return $out;
    }

    private function user(int $id): array
    {
        return Db::one('SELECT * FROM users WHERE id = :id', ['id' => $id]);
    }

    private function assertNotFound(callable $fn, string $message): void
    {
        try {
            $fn();
            self::fail($message . ': expected a 404');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status, $message);
        }
    }

    /**
     * A school with a section (English and Science) and a club outside it. Andrea takes all three; Ben takes
     * the section's two classes and hands nothing in; a third student is only in the club.
     * @return array<string,int>
     */
    private function school(): array
    {
        $org = Db::insert('organizations', ['name' => 'Report Test School', 'slug' => 'report-test', 'timezone' => 'Asia/Manila', 'is_demo' => 1]);
        Settings::override($org, 'grading', Settings::gradingPresets()['points']['value']);
        $year = Db::insert('academic_periods', ['organization_id' => $org, 'name' => 'School year 2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-09', 'is_current' => 1]);
        $q1 = Db::insert('grading_periods', ['organization_id' => $org, 'academic_period_id' => $year, 'name' => 'Quarter 1', 'position' => 1, 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-31']);
        $q2 = Db::insert('grading_periods', ['organization_id' => $org, 'academic_period_id' => $year, 'name' => 'Quarter 2', 'position' => 2, 'starts_on' => '2026-09-01', 'ends_on' => '2026-11-07']);
        $person = static function (string $role, string $given, string $family) use ($org): int {
            return Db::insert('users', ['organization_id' => $org, 'role' => $role, 'given_name' => $given, 'family_name' => $family,
                'username' => strtolower($given . '.' . $family) . '.rt', 'status' => 'active', 'is_demo' => 1]);
        };
        $adviser = $person('teacher', 'Ana', 'Adviser');
        $englishTeacher = $person('teacher', 'Eli', 'English');
        $section = Db::insert('sections', ['organization_id' => $org, 'academic_period_id' => $year, 'grade_level' => 'Grade 9', 'name' => 'Report', 'adviser_id' => $adviser]);
        $class = static function (string $subject, string $name, ?int $sectionId, int $teacher) use ($org, $year): int {
            $subjectId = Db::insert('subjects', ['organization_id' => $org, 'name' => $subject]);
            $id = Db::insert('classes', ['organization_id' => $org, 'subject_id' => $subjectId, 'academic_period_id' => $year, 'section_id' => $sectionId, 'name' => $name, 'status' => 'active']);
            Db::insert('class_memberships', ['class_id' => $id, 'user_id' => $teacher, 'role' => 'teacher', 'status' => 'active']);
            return $id;
        };
        $english = $class('English', 'English 9 – Report', $section, $englishTeacher);
        $science = $class('Science', 'Science 9 – Report', $section, $adviser);
        $journalism = $class('Journalism', 'Journalism Club', null, $englishTeacher);
        $andrea = $person('student', 'Andrea', 'Report');
        $ben = $person('student', 'Ben', 'Report');
        $clubOnly = $person('student', 'Carla', 'Club');
        foreach ([[$andrea, $english], [$andrea, $science], [$andrea, $journalism], [$ben, $english], [$ben, $science], [$clubOnly, $journalism]] as [$who, $where]) {
            Db::insert('class_memberships', ['class_id' => $where, 'user_id' => $who, 'role' => 'student', 'status' => 'active']);
        }
        // [class, points, due, Andrea's score or null, released, late, comment]
        $work = [
            [$science, 20, '2026-07-10 01:00:00', 18, true, false, 'Clear and careful.'],
            [$science, 10, '2026-07-20 01:00:00', 2, false, false, 'Not yet shared.'],
            [$science, 10, '2026-08-01 01:00:00', null, false, false, null],
            [$science, 20, '2026-08-20 01:00:00', 15, true, true, 'Late, but complete.'],
            [$science, 10, '2026-09-15 01:00:00', 9, true, false, 'Second quarter comment.'],
            [$english, 10, '2026-07-15 01:00:00', 7, true, false, null],
            [$journalism, 10, '2026-07-15 01:00:00', 10, true, false, null],
        ];
        foreach ($work as $i => [$classId, $points, $due, $score, $released, $late, $comment]) {
            $a = Db::insert('assignments', ['organization_id' => $org, 'class_id' => $classId, 'kind' => 'assignment', 'title' => 'Task ' . ($i + 1),
                'status' => 'published', 'due_at' => $due, 'points_possible' => $points, 'created_by' => $adviser]);
            if ($score !== null) {
                Db::insert('submissions', ['organization_id' => $org, 'assignment_id' => $a, 'student_id' => $andrea, 'status' => 'submitted', 'submitted_at' => $due,
                    'is_late' => $late ? 1 : 0, 'score' => $score, 'points_possible' => $points, 'grading_status' => 'graded', 'feedback' => $comment,
                    'feedback_status' => $released ? 'released' : 'draft', 'released_at' => $released ? $due : null]);
            }
        }
        return ['org' => $org, 'year' => $year, 'q1' => $q1, 'q2' => $q2, 'section' => $section, 'adviser' => $adviser, 'english_teacher' => $englishTeacher,
            'english' => $english, 'science' => $science, 'journalism' => $journalism, 'andrea' => $andrea, 'ben' => $ben, 'clubOnly' => $clubOnly];
    }
}
