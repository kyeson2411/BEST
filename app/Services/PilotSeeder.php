<?php

declare(strict_types=1);

namespace Sibol\Services;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Config;
use Sibol\Core\Db;

/**
 * A pilot-size fictional school for timing pages under realistic load: 1,200 students in 24 sections
 * (Grades 7 to 10), 60 teachers and two administrators, eight subjects per section, and ten weeks of work in
 * every class (materials, assignments, quizzes, work on paper, submissions, grades, sign-ins, notifications,
 * companion and practice counts, feedback). Everything is invented and flagged is_demo; the demo sign-in buttons
 * use the first account of each role. Rows are written in bulk, so it takes about a minute.
 * Materials are links (no indexed passages): this school is for measuring, not for trying the companion.
 *   php bin/sibol.php db:reset --size=pilot
 */
final class PilotSeeder
{
    public const PASSWORD = DemoSeeder::PASSWORD;
    private const TZ = 'Asia/Manila';
    private const DOMAIN = 'pilot.sibol.test';
    private const GRADES = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'];
    private const SECTIONS = ['Narra', 'Molave', 'Yakal', 'Acacia', 'Mahogany', 'Kamagong'];
    private const STUDENTS_PER_SECTION = 50;
    /** name => [code, teachers] (60 teachers in all) */
    private const SUBJECTS = [
        'English' => ['ENG', 8], 'Filipino' => ['FIL', 8], 'Mathematics' => ['MATH', 8], 'Science' => ['SCI', 8],
        'Araling Panlipunan' => ['AP', 7], 'MAPEH' => ['MAPEH', 7], 'TLE' => ['TLE', 7], 'Edukasyon sa Pagpapakatao' => ['ESP', 7],
    ];
    private const WEEKS = 10;
    private const GIVEN = [
        'Andrea', 'Miguel', 'Bea', 'Joshua', 'Patricia', 'Carlo', 'Nicole', 'Paolo', 'Sofia', 'Gabriel', 'Kyla', 'Enzo',
        'Janelle', 'Marco', 'Trisha', 'Daniel', 'Bianca', 'Luis', 'Angela', 'Rafael', 'Camille', 'Jerome', 'Danica', 'Kevin',
        'Princess', 'Mark', 'Erika', 'John', 'Kristine', 'Paul', 'Jasmine', 'Christian', 'Mae', 'Adrian', 'Rica', 'Vincent',
        'Hazel', 'Joaquin', 'Aira', 'Bryan', 'Shaira', 'Ivan', 'Lorraine', 'Neil', 'Clarisse', 'Renz', 'Ysabel', 'Lance',
        'Maricel', 'Gerald', 'Joy', 'Ramon', 'Liza', 'Teresa', 'Ana', 'Jose', 'Rosa', 'Ernesto', 'Celia', 'Arnel',
    ];
    private const FAMILY = [
        'Cruz', 'Bautista', 'Ramos', 'Garcia', 'Lim', 'Reyes', 'Tan', 'Aquino', 'Dela Cruz', 'Torres', 'Navarro', 'Castillo',
        'Ocampo', 'Villareal', 'Mendoza', 'Soriano', 'Flores', 'Pascual', 'Santos', 'Domingo', 'Mercado', 'Dizon', 'Santiago',
        'Villanueva', 'Gonzales', 'Rivera', 'Aguilar', 'Salazar', 'Panganiban', 'Manalo', 'Del Rosario', 'Valdez', 'Ignacio',
        'Morales', 'Fernandez', 'Javier', 'Gutierrez', 'Robles', 'Padilla', 'Sison', 'Lopez', 'Perez', 'Marquez', 'Abad',
        'Villamor', 'Mallari', 'Dimaculangan', 'Macaraeg', 'Ventura', 'Cabrera', 'Tolentino', 'Lacson', 'Galang', 'Esguerra',
    ];

    private int $org = 0;
    private int $period = 0;
    private string $hash = '';
    private DateTimeImmutable $now;
    /** @var array<string,true> */
    private array $usernames = [];
    /** @var list<int> */
    private array $admins = [];
    /** @var list<int> */
    private array $teachers = [];
    /** @var list<array{id:int,grade:string,name:string,adviser:int,students:list<int>}> */
    private array $sections = [];
    /** @var array<int,float> student id => ability */
    private array $ability = [];

    public function run(): string
    {
        if ((int) Db::value('SELECT COUNT(*) FROM organizations') > 0) {
            throw new \RuntimeException('This database already contains a school. Run "php bin/sibol.php db:reset --size=pilot" to start over.');
        }
        $started = microtime(true);
        mt_srand(20261020);
        $this->hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $this->now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        Db::transaction(function (): void {
            $this->school();
            $this->people();
            $this->classes();
        });
        $counts = Db::one(
            'SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM classes) AS classes, (SELECT COUNT(*) FROM learning_materials) AS materials,
                    (SELECT COUNT(*) FROM assignments) AS assignments, (SELECT COUNT(*) FROM submissions) AS submissions'
        );
        Settings::flush();
        return sprintf(
            'Pilot-size demo school created in %d s: %d people (%d students), %d classes, %d materials, %d assignments, %d submissions. Demo password: see README.md.',
            (int) round(microtime(true) - $started),
            (int) $counts['users'],
            count($this->ability),
            (int) $counts['classes'],
            (int) $counts['materials'],
            (int) $counts['assignments'],
            (int) $counts['submissions']
        );
    }

    // ------------------------------------------------------------------------------------------ school

    private function school(): void
    {
        $school = trim((string) Config::get('school.name', '')) ?: 'Sibol Demo School';
        $this->org = Db::insert('organizations', [
            'name' => $school . ' (Demo, pilot size)',
            'short_name' => mb_substr((trim((string) Config::get('school.short_name', '')) ?: 'Sibol') . ' Pilot', 0, 60),
            'slug' => 'pilot-school',
            'timezone' => self::TZ,
            'default_language' => 'en',
            'is_demo' => 1,
            'created_at' => $this->ago(120 * 86400),
            'updated_at' => $this->ago(120 * 86400),
        ]);
        Settings::set($this->org, 'grading', Settings::gradingPresets()['k12']['value']);
        Settings::set($this->org, 'grade_levels', self::GRADES);
        Settings::set($this->org, 'privacy', ['contact_name' => 'Data Protection Officer (fictional)', 'contact_email' => 'privacy@' . self::DOMAIN]);
        $local = $this->now->setTimezone(new DateTimeZone(self::TZ));
        $startYear = (int) $local->format('n') >= 6 ? (int) $local->format('Y') : (int) $local->format('Y') - 1;
        $this->period = Db::insert('academic_periods', [
            'organization_id' => $this->org, 'name' => sprintf('School year %d–%d', $startYear, $startYear + 1),
            'starts_on' => $startYear . '-06-15', 'ends_on' => ($startYear + 1) . '-04-09', 'is_current' => 1,
            'created_at' => $this->ago(120 * 86400), 'updated_at' => $this->ago(120 * 86400),
        ]);
    }

    private function people(): void
    {
        $person = function (string $role, int $n) {
            $given = self::GIVEN[($n * 7 + ($role === 'student' ? 0 : 3)) % count(self::GIVEN)];
            $family = self::FAMILY[($n * 11 + intdiv($n, count(self::GIVEN))) % count(self::FAMILY)];
            $base = Usernames::base($given, $family);
            $username = $base;
            for ($i = 2; isset($this->usernames[$username]); $i++) {
                $username = $base . $i;
            }
            $this->usernames[$username] = true;
            return [
                'organization_id' => $this->org, 'role' => $role, 'status' => 'active',
                'email' => $role === 'student' ? null : $username . '@' . self::DOMAIN,
                'username' => $username, 'password_hash' => $this->hash, 'given_name' => $given, 'family_name' => $family,
                'grade_level' => null, 'is_demo' => 1, 'last_login_at' => $this->ago(mt_rand(1, 72) * 3600),
                'password_changed_at' => $this->ago(100 * 86400), 'created_at' => $this->ago(110 * 86400), 'updated_at' => $this->ago(30 * 86400),
            ];
        };
        // Administrators first, then teachers, then students: the demo buttons sign in as the first of each role.
        $this->admins = $this->insertUsers([$person('admin', 1), $person('admin', 2)]);
        $staff = [];
        for ($i = 1; $i <= 60; $i++) {
            $staff[] = $person('teacher', $i + 2);
        }
        $this->teachers = $this->insertUsers($staff);
        $n = 0;
        foreach (self::GRADES as $g => $grade) {
            foreach (self::SECTIONS as $s => $name) {
                $rows = [];
                for ($i = 0; $i < self::STUDENTS_PER_SECTION; $i++) {
                    $rows[] = ['grade_level' => $grade] + $person('student', $n++);
                }
                $ids = $this->insertUsers($rows);
                foreach ($ids as $id) {
                    $this->ability[$id] = 0.45 + mt_rand(0, 50) / 100;
                }
                $this->sections[] = [
                    'id' => (int) Sections::forClass($this->org, $this->period, $grade, $name),
                    'grade' => $grade, 'name' => $name, 'adviser' => $this->teachers[count($this->sections)], 'students' => $ids,
                ];
            }
        }
        foreach ($this->sections as $section) {
            Db::update('sections', ['adviser_id' => $section['adviser']], ['id' => $section['id']]);
        }
        $acks = [];
        foreach (array_keys($this->ability) as $student) {
            foreach (Notices::current($this->org) as $notice => $version) {
                $acks[] = ['organization_id' => $this->org, 'user_id' => $student, 'notice' => $notice, 'version' => $version, 'acknowledged_at' => $this->ago(100 * 86400)];
            }
        }
        $this->bulk('notice_acknowledgments', $acks);
    }

    /** @param list<array<string,mixed>> $rows @return list<int> new ids in order */
    private function insertUsers(array $rows): array
    {
        $this->bulk('users', $rows);
        [$in, $params] = Db::in(array_column($rows, 'username'), 'n');
        $ids = [];
        foreach (Db::all("SELECT id, username FROM users WHERE username IN $in", $params) as $r) {
            $ids[$r['username']] = (int) $r['id'];
        }
        return array_map(static fn ($row) => $ids[$row['username']], $rows);
    }

    // ------------------------------------------------------------------------------------------ classes and work

    private function classes(): void
    {
        $subjects = [];
        $offset = 0;
        foreach (self::SUBJECTS as $name => [$code, $count]) {
            $subjects[] = ['id' => Db::insert('subjects', ['organization_id' => $this->org, 'name' => $name, 'code' => $code]), 'name' => $name, 'teachers' => array_slice($this->teachers, $offset, $count)];
            $offset += $count;
        }
        foreach ($this->sections as $j => $section) {
            foreach ($subjects as $subject) {
                $teacher = $subject['teachers'][intdiv($j * count($subject['teachers']), count($this->sections))];
                $classId = Db::insert('classes', [
                    'organization_id' => $this->org, 'subject_id' => $subject['id'], 'academic_period_id' => $this->period,
                    'name' => sprintf('%s %s – %s', $subject['name'], substr($section['grade'], 6), $section['name']),
                    'section' => $section['name'], 'section_id' => $section['id'], 'grade_level' => $section['grade'],
                    'status' => 'active', 'created_by' => $this->admins[0], 'created_at' => $this->ago(110 * 86400), 'updated_at' => $this->ago(110 * 86400),
                ]);
                $members = [['class_id' => $classId, 'user_id' => $teacher, 'role' => 'teacher', 'status' => 'active', 'joined_via' => 'admin', 'joined_at' => $this->ago(110 * 86400)]];
                foreach ($section['students'] as $student) {
                    $members[] = ['class_id' => $classId, 'user_id' => $student, 'role' => 'student', 'status' => 'active', 'joined_via' => 'import', 'joined_at' => $this->ago(105 * 86400)];
                }
                $this->bulk('class_memberships', $members);
                $this->work($classId, $subject['name'], $teacher, $section['students']);
            }
        }
        $this->activity();
    }

    /** Ten weeks in one class: six materials, four quizzes, six assignments and two tasks on paper, with results. */
    private function work(int $classId, string $subject, int $teacher, array $students): void
    {
        $modules = [];
        foreach ([1, 2] as $m) {
            $modules[] = Db::insert('modules', ['class_id' => $classId, 'title' => "Module $m: $subject", 'position' => $m]);
        }
        $materials = [];
        for ($i = 0; $i < 6; $i++) {
            $published = $this->ago((self::WEEKS * 7 - $i * 11) * 86400);
            $materials[] = [
                'organization_id' => $this->org, 'class_id' => $classId, 'module_id' => $modules[intdiv($i, 3)], 'title' => "$subject notes, part " . ($i + 1),
                'kind' => 'link', 'url' => 'https://example.org/' . rawurlencode(strtolower($subject)) . '/notes-' . ($i + 1), 'visibility' => 'published',
                'ai_enabled' => 1, 'created_by' => $teacher, 'published_at' => $published, 'created_at' => $published, 'updated_at' => $published,
            ];
        }
        $this->bulk('learning_materials', $materials);

        $plan = ['quiz', 'assignment', 'assignment', 'paper', 'quiz', 'assignment', 'assignment', 'quiz', 'assignment', 'paper', 'quiz', 'assignment'];
        $submissions = [];
        $answers = [];
        foreach ($plan as $i => $kind) {
            // Due dates spread over the ten weeks; the last two are still ahead.
            $due = $this->ago((int) round((self::WEEKS * 7 - 4 - $i * 6.2) * 86400) - 9 * 3600);
            $published = $this->shift($due, -7 * 86400);
            $title = ['quiz' => 'Quiz', 'assignment' => 'Written task', 'paper' => 'Test on paper'][$kind] . ' ' . ($i + 1) . ": $subject";
            $questions = $kind === 'quiz' ? $this->quizQuestions($subject, $i) : [];
            $points = $kind === 'quiz' ? 10.0 : ($kind === 'paper' ? 30.0 : 20.0);
            $assignmentId = Db::insert('assignments', [
                'organization_id' => $this->org, 'class_id' => $classId, 'module_id' => $modules[$i < 6 ? 0 : 1], 'kind' => $kind, 'title' => $title,
                'instructions' => $kind === 'paper' ? 'Done in class on paper.' : 'Answer every part. Show your reasoning.',
                'category' => $kind === 'quiz' ? 'written' : ($kind === 'paper' ? 'periodic' : 'performance'),
                'status' => 'published', 'due_at' => $due, 'late_policy' => 'accepted', 'points_possible' => $points,
                'allow_text' => $kind === 'assignment' ? 1 : 0, 'feedback_release' => $kind === 'quiz' ? 'immediate' : 'manual',
                'answer_key_release' => 'after_due', 'created_by' => $teacher, 'published_at' => $published,
                'created_at' => $published, 'updated_at' => $published,
            ]);
            $snapshot = [];
            foreach ($questions as $p => $q) {
                $q['id'] = Db::insert('questions', $q['row'] + ['assignment_id' => $assignmentId, 'position' => $p + 1, 'reviewed' => 1]);
                $questions[$p] = $q;
                $snapshot[] = ['id' => $q['id'], 'qtype' => $q['row']['qtype'], 'prompt' => $q['row']['prompt'], 'points' => (float) $q['row']['points'],
                    'options' => $q['row']['options'], 'answer_key' => $q['row']['answer_key'], 'explanation' => null, 'topic' => $q['row']['topic']];
            }
            $version = Db::insert('assignment_versions', [
                'assignment_id' => $assignmentId, 'version_no' => 1, 'published_by' => $teacher, 'created_at' => $published,
                'snapshot' => (string) json_encode(['title' => $title, 'points_possible' => $points, 'questions' => $snapshot], JSON_UNESCAPED_UNICODE),
            ]);
            Db::update('assignments', ['current_version_id' => $version], ['id' => $assignmentId]);
            if ($due > $this->utcNow()) {
                continue;
            }
            $recent = $due > $this->ago(8 * 86400);
            foreach ($students as $s => $student) {
                $ability = $this->ability[$student];
                if (mt_rand(1, 100) > ($kind === 'paper' ? 97 : (int) round(70 + $ability * 25))) {
                    continue; // not handed in: missing
                }
                $late = $kind !== 'paper' && mt_rand(1, 100) <= 8;
                $at = $this->shift($due, $late ? mt_rand(1, 48) * 3600 : -mt_rand(1, 96) * 3600);
                $score = round($points * max(0.2, min(1.0, $ability + (mt_rand(-15, 15) / 100))), 0);
                $graded = $kind !== 'assignment' || !$recent || mt_rand(1, 100) <= 30;
                $key = $assignmentId . ':' . $student;
                $submissions[$key] = [
                    'organization_id' => $this->org, 'assignment_id' => $assignmentId, 'student_id' => $student, 'assignment_version_id' => $version,
                    'attempt_no' => 1, 'status' => 'submitted', 'source' => $kind === 'paper' ? 'paper' : 'online',
                    'body' => $kind === 'assignment' ? 'My answer to the task, in my own words, with an example from class.' : null,
                    'started_at' => $this->shift($at, -1800), 'submitted_at' => $at, 'is_late' => $late ? 1 : 0,
                    'auto_score' => $kind === 'quiz' ? $score : null, 'score' => $graded ? $score : null, 'points_possible' => $points,
                    'grading_status' => $graded ? 'graded' : 'needs_review',
                    'feedback' => $graded && $kind === 'assignment' ? 'Clear explanation. Next time, add one more example from the module.' : null,
                    'feedback_status' => $graded ? ($kind === 'paper' && $recent ? 'draft' : 'released') : 'none',
                    'released_at' => $graded && !($kind === 'paper' && $recent) ? $this->shift($at, 86400) : null,
                    'graded_by' => $graded ? $teacher : null, 'graded_at' => $graded ? $this->shift($at, 86400) : null,
                    'created_at' => $at, 'updated_at' => $at,
                ];
                foreach ($questions as $q) {
                    $correct = mt_rand(1, 100) <= (int) round($ability * 100);
                    $answers[] = [$key, $q, $correct];
                }
            }
        }
        $this->bulk('submissions', array_values($submissions));
        if ($answers) {
            $ids = [];
            [$in, $params] = Db::in(array_values(array_unique(array_map(static fn ($k) => (int) explode(':', $k)[0], array_keys($submissions)))), 'a');
            foreach (Db::all("SELECT id, assignment_id, student_id FROM submissions WHERE assignment_id IN $in", $params) as $r) {
                $ids[$r['assignment_id'] . ':' . $r['student_id']] = (int) $r['id'];
            }
            $rows = [];
            foreach ($answers as [$key, $q, $correct]) {
                $right = $q['correct'];
                $rows[] = [
                    'submission_id' => $ids[$key], 'question_id' => $q['id'],
                    'response' => $correct ? $right : $q['wrong'], 'is_correct' => $correct ? 1 : 0,
                    'points_awarded' => $correct ? $q['row']['points'] : 0, 'needs_manual' => 0,
                ];
            }
            $this->bulk('submission_answers', $rows);
        }
    }

    /** Five automatically marked questions on two topics. @return list<array{row:array,correct:string,wrong:string}> */
    private function quizQuestions(string $subject, int $n): array
    {
        $topics = ["$subject: key ideas", "$subject: applying the ideas"];
        $out = [];
        for ($i = 0; $i < 5; $i++) {
            if ($i === 4) {
                $out[] = ['row' => ['qtype' => 'true_false', 'prompt' => "Statement $n.$i about $subject. True or false?", 'points' => 2, 'options' => null,
                    'answer_key' => (string) json_encode(['correct' => true]), 'topic' => $topics[1]], 'correct' => 'true', 'wrong' => 'false'];
                continue;
            }
            $options = [];
            foreach (['A', 'B', 'C', 'D'] as $k => $letter) {
                $options[] = ['key' => $letter, 'text' => "Choice $letter"];
            }
            $right = ['A', 'B', 'C', 'D'][($n + $i) % 4];
            $out[] = ['row' => ['qtype' => 'multiple_choice', 'prompt' => "Question $n.$i about $subject.", 'points' => 2,
                'options' => (string) json_encode($options), 'answer_key' => (string) json_encode(['correct' => $right]), 'topic' => $topics[$i % 2]],
                'correct' => $right, 'wrong' => $right === 'A' ? 'B' : 'A'];
        }
        return $out;
    }

    // ------------------------------------------------------------------------------------------ everyday activity

    /** Sign-ins, opened materials, notifications, companion and practice counts and a little feedback, over ten weeks. */
    private function activity(): void
    {
        $students = array_keys($this->ability);
        $logins = [];
        foreach (array_merge($this->teachers, $this->admins) as $staff) {
            for ($d = 0; $d < self::WEEKS * 7; $d += mt_rand(1, 3)) {
                $logins[] = ['organization_id' => $this->org, 'actor_id' => $staff, 'actor_role' => in_array($staff, $this->admins, true) ? 'admin' : 'teacher',
                    'action' => 'auth.login', 'created_at' => $this->ago($d * 86400 + mt_rand(0, 36000))];
            }
        }
        foreach ($students as $student) {
            for ($d = mt_rand(0, 3); $d < self::WEEKS * 7; $d += mt_rand(2, 5)) {
                $logins[] = ['organization_id' => $this->org, 'actor_id' => $student, 'actor_role' => 'student', 'action' => 'auth.login', 'created_at' => $this->ago($d * 86400 + mt_rand(0, 36000))];
            }
        }
        $this->bulk('audit_logs', $logins);

        // Opened materials and new-work notifications for each student's classes.
        $views = [];
        $notes = [];
        $byClass = Db::all(
            "SELECT cm.user_id, m.id AS material_id, m.class_id, m.published_at FROM class_memberships cm
             JOIN learning_materials m ON m.class_id = cm.class_id WHERE cm.role = 'student' AND m.organization_id = :o",
            ['o' => $this->org]
        );
        foreach ($byClass as $r) {
            if (mt_rand(1, 100) <= 70) {
                $at = $this->shift((string) $r['published_at'], mt_rand(1, 72) * 3600);
                $views[] = ['user_id' => (int) $r['user_id'], 'content_type' => 'material', 'content_id' => (int) $r['material_id'], 'class_id' => (int) $r['class_id'],
                    'view_count' => mt_rand(1, 4), 'first_viewed_at' => $at, 'last_viewed_at' => $at];
            }
        }
        $this->bulk('content_views', $views);
        $recent = Db::all(
            "SELECT cm.user_id, a.id, a.title, a.kind, a.published_at FROM assignments a JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.role = 'student'
             WHERE a.organization_id = :o AND a.published_at >= :since",
            ['o' => $this->org, 'since' => $this->ago(21 * 86400)]
        );
        foreach ($recent as $r) {
            $notes[] = ['organization_id' => $this->org, 'user_id' => (int) $r['user_id'], 'type' => 'assignment',
                'title' => mb_substr(($r['kind'] === 'quiz' ? 'New quiz: ' : 'New assignment: ') . $r['title'], 0, 200), 'body' => null,
                'link' => '/student/assignments/' . (int) $r['id'], 'read_at' => mt_rand(1, 100) <= 70 ? $this->shift((string) $r['published_at'], 7200) : null,
                'created_at' => (string) $r['published_at']];
        }
        $this->bulk('notifications', $notes);

        // Companion questions and practice sets, as counts (no conversations), and feedback.
        $events = [];
        $practice = [];
        $feedback = [];
        foreach ($students as $i => $student) {
            if ($i % 5 < 2) {
                for ($w = 0; $w < self::WEEKS; $w++) {
                    for ($k = mt_rand(0, 3); $k > 0; $k--) {
                        $events[] = ['organization_id' => $this->org, 'user_id' => $student, 'user_role' => 'student', 'feature' => 'companion', 'provider' => 'demo',
                            'model' => 'demo', 'is_demo' => 1, 'status' => 'ok', 'created_at' => $this->ago(($w * 7 + mt_rand(0, 6)) * 86400 + mt_rand(0, 36000))];
                    }
                }
            }
            if ($i % 7 === 0) {
                $at = $this->ago(mt_rand(0, self::WEEKS * 7) * 86400);
                $practice[] = ['organization_id' => $this->org, 'user_id' => $student, 'kind' => 'quiz', 'title' => 'Practice: key ideas', 'items' => '[]',
                    'generator' => 'demo', 'score' => mt_rand(2, 5), 'total' => 5, 'completed_at' => $at, 'created_at' => $at, 'updated_at' => $at];
            }
            if ($i % 25 === 0) {
                $at = $this->ago(mt_rand(0, self::WEEKS * 7) * 86400);
                $feedback[] = ['organization_id' => $this->org, 'user_id' => $student, 'user_role' => 'student', 'category' => SiteFeedback::CATEGORIES[$i % 3],
                    'body' => 'The page was slow to open on mobile data.', 'page_path' => '/student', 'status' => 'new', 'created_at' => $at, 'updated_at' => $at];
            }
        }
        $this->bulk('ai_usage_events', $events);
        $this->bulk('practice_sets', $practice);
        $this->bulk('site_feedback', $feedback);
    }

    // ------------------------------------------------------------------------------------------ helpers

    /**
     * Inserts rows in batches (all rows have the same keys, in the same order).
     * @param list<array<string,mixed>> $rows
     */
    private function bulk(string $table, array $rows, int $chunk = 500): void
    {
        if (!$rows) {
            return;
        }
        $columns = array_keys($rows[0]);
        $one = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        foreach (array_chunk($rows, $chunk) as $part) {
            $values = [];
            foreach ($part as $row) {
                foreach ($columns as $column) {
                    $values[] = $row[$column];
                }
            }
            Db::pdo()->prepare(
                'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', array_fill(0, count($part), $one))
            )->execute($values);
        }
    }

    private function ago(int $seconds): string
    {
        return $this->now->modify('-' . $seconds . ' seconds')->format('Y-m-d H:i:s');
    }

    private function shift(string $utc, int $seconds): string
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds')->format('Y-m-d H:i:s');
    }

    private function utcNow(): string
    {
        return $this->now->format('Y-m-d H:i:s');
    }
}
