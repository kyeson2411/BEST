<?php

declare(strict_types=1);

namespace Sibol\Services;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Auth;
use Sibol\Core\Config;
use Sibol\Core\Dates;
use Sibol\Core\Db;
use Sibol\Core\Uploads;
use Sibol\Services\Ai\Companion;
use Sibol\Services\Ai\DemoProvider;
use Sibol\Services\Ai\Providers;

/**
 * Deterministic, clearly fictional demo school. Dates are relative to "now" so the
 * dashboards always have upcoming and past work. Every record is flagged is_demo.
 * All accounts share the demo password documented in README.md.
 */
final class DemoSeeder
{
    public const PASSWORD = 'Sibol-demo-2026';
    private const TZ = 'Asia/Manila';
    private const DOMAIN = 'demo.sibol.test';

    private int $org = 0;
    private string $hash = '';
    /** @var array<string,int> */
    private array $u = [];
    /** @var array<string,array<string,mixed>> */
    private array $users = [];
    /** @var array<string,int> */
    private array $c = [];
    /** @var array<string,int> */
    private array $mod = [];
    /** @var array<string,int> */
    private array $mat = [];
    /** @var list<int> */
    private array $versions = [];
    /** @var array<string,int> */
    private array $a = [];
    /** @var array<string,list<string>> section => student keys */
    private array $sections = [];
    /** @var array<string,float> */
    private array $ability = [];

    public function run(): string
    {
        if ((int) Db::value('SELECT COUNT(*) FROM organizations') > 0) {
            throw new \RuntimeException('This database already contains a school. Run "php bin/sibol.php db:reset" to start over.');
        }
        Dates::setTimezone(self::TZ);
        mt_srand(20261017);
        $this->hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);

        Db::transaction(function (): void {
            $this->school();
            $this->people();
            $this->structure();
            $this->classes();
            $this->materials();
            $this->lessons();
            $this->announcements();
        });
        foreach ($this->versions as $versionId) {
            Ingestion::process($versionId);
        }
        Db::transaction(function (): void {
            $this->assignments();
            $this->competencies();
            $this->activity();
        });
        $this->companion();
        Db::exec('DELETE FROM audit_logs WHERE organization_id = :o OR organization_id IS NULL', ['o' => $this->org]);
        Db::exec('DELETE FROM notifications WHERE organization_id = :o', ['o' => $this->org]);
        Db::transaction(function (): void {
            $this->notifications();
            $this->auditTrail();
            $this->feedback();
            $this->materialGaps();
        });
        Auth::actingAs(null);
        Auth::refresh();
        Dates::setTimezone(null);
        Settings::flush();

        $counts = Db::one(
            'SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM classes) AS classes,
                    (SELECT COUNT(*) FROM learning_materials) AS materials, (SELECT COUNT(*) FROM material_chunks) AS chunks,
                    (SELECT COUNT(*) FROM assignments) AS assignments, (SELECT COUNT(*) FROM submissions) AS submissions'
        );
        return sprintf(
            'Demo school created: %d people, %d classes, %d materials (%d indexed passages), %d assignments, %d submissions. Demo password: see README.md.',
            $counts['users'], $counts['classes'], $counts['materials'], $counts['chunks'], $counts['assignments'], $counts['submissions']
        );
    }

    // ---------------------------------------------------------------- helpers

    /** Local Manila time, relative to today, converted to UTC. */
    private function at(string $relativeDay, string $time = '23:59'): string
    {
        $d = (new DateTimeImmutable('now', new DateTimeZone(self::TZ)))->modify($relativeDay);
        [$h, $m] = array_map('intval', explode(':', $time));
        return $d->setTime($h, $m)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** UTC timestamp relative to now, e.g. "-3 days -2 hours". */
    private function ago(string $relative): string
    {
        return gmdate('Y-m-d H:i:s', (int) strtotime($relative));
    }

    private function actAs(string $key): void
    {
        Auth::actingAs($this->users[$key]);
    }

    // ---------------------------------------------------------------- school & people

    private function school(): void
    {
        // Named after the school in config (SCHOOL_NAME); the people and records stay fictional.
        $school = trim((string) Config::get('school.name', '')) ?: 'Sibol Demo School';
        $this->org = Db::insert('organizations', [
            'name' => $school,
            'short_name' => mb_substr((trim((string) Config::get('school.short_name', '')) ?: 'Sibol'), 0, 60),
            'slug' => 'demo-school',
            'timezone' => self::TZ,
            'default_language' => 'en',
            'is_demo' => 1,
            'created_at' => $this->ago('-120 days'),
            'updated_at' => $this->ago('-120 days'),
        ]);
        Settings::set($this->org, 'grading', Settings::gradingPresets()['k12']['value']);
        Settings::set($this->org, 'grade_levels', ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10']);
        Settings::set($this->org, 'privacy', [
            'contact_name' => 'Grace Lim, Data Protection Officer (fictional)',
            'contact_email' => 'privacy@' . self::DOMAIN,
        ]);
    }

    /** Staff have school email; students, like many here, sign in with a username only. */
    private function person(string $key, string $role, string $given, string $family, array $extra = []): int
    {
        $username = Usernames::forPerson($given, $family);
        $email = $role === 'student' ? null : ($extra['email'] ?? $username . '@' . self::DOMAIN);
        $status = $extra['status'] ?? 'active';
        $row = [
            'organization_id' => $this->org,
            'role' => $role,
            'status' => $status,
            'email' => $email,
            'username' => $username,
            'password_hash' => $status === 'invited' ? null : $this->hash,
            'given_name' => $given,
            'family_name' => $family,
            'preferred_name' => $extra['preferred_name'] ?? null,
            'grade_level' => $extra['grade_level'] ?? null,
            'language' => $extra['language'] ?? null,
            'is_demo' => 1,
            'last_login_at' => $status === 'active' ? $this->ago('-' . mt_rand(1, 40) . ' hours') : null,
            'password_changed_at' => $status === 'invited' ? null : $this->ago('-100 days'),
            'deactivated_at' => $status === 'deactivated' ? $this->ago('-20 days') : null,
            'created_at' => $this->ago('-110 days'),
            'updated_at' => $this->ago('-30 days'),
        ];
        $id = Db::insert('users', $row);
        $this->u[$key] = $id;
        $this->users[$key] = Db::one('SELECT u.*, o.timezone AS org_timezone, o.name AS org_name FROM users u JOIN organizations o ON o.id = u.organization_id WHERE u.id = :id', ['id' => $id]);
        return $id;
    }

    private function people(): void
    {
        $this->person('admin', 'admin', 'Teresa', 'Villanueva', ['preferred_name' => 'Ma. Teresa']);
        $this->person('ramon', 'teacher', 'Ramon', 'Dizon');
        $this->person('liza', 'teacher', 'Liza', 'Mercado');
        $this->person('joy', 'teacher', 'Joy', 'Santiago');
        $this->person('carmela', 'teacher', 'Carmela', 'Reyes', ['status' => 'invited']);

        $narra = [
            ['andrea', 'Andrea', 'Cruz', 0.84], ['miguel', 'Miguel', 'Bautista', 0.71], ['bea', 'Bea', 'Ramos', 0.90],
            ['joshua', 'Joshua', 'Garcia', 0.58], ['patricia', 'Patricia', 'Lim', 0.77], ['carlo', 'Carlo', 'Reyes', 0.49],
            ['nicole', 'Nicole', 'Tan', 0.86], ['paolo', 'Paolo', 'Aquino', 0.55], ['sofia', 'Sofia', 'Dela Cruz', 0.80],
            ['gabriel', 'Gabriel', 'Torres', 0.66], ['kyla', 'Kyla', 'Navarro', 0.74], ['enzo', 'Enzo', 'Castillo', 0.62],
        ];
        $molave = [
            ['janelle', 'Janelle', 'Ocampo', 0.79], ['marco', 'Marco', 'Villareal', 0.61], ['trisha', 'Trisha', 'Mendoza', 0.83],
            ['daniel', 'Daniel', 'Soriano', 0.57], ['bianca', 'Bianca', 'Flores', 0.72], ['luis', 'Luis', 'Pascual', 0.68],
        ];
        foreach ([['narra', $narra], ['molave', $molave]] as [$section, $list]) {
            foreach ($list as [$key, $given, $family, $ability]) {
                $this->person($key, 'student', $given, $family, ['grade_level' => 'Grade 9']);
                $this->sections[$section][] = $key;
                $this->ability[$key] = $ability;
            }
        }
        $this->person('rafael', 'student', 'Rafael', 'Domingo', ['status' => 'invited', 'grade_level' => 'Grade 9']);
        $this->person('angelica', 'student', 'Angelica', 'Santos', ['status' => 'deactivated', 'grade_level' => 'Grade 9']);
        // Students who have been using Sibol accepted the current privacy notice and rules when they started.
        foreach ($this->u as $key => $id) {
            if ($this->users[$key]['role'] === 'student' && $this->users[$key]['status'] === 'active') {
                foreach (Notices::current($this->org) as $notice => $version) {
                    Db::insert('notice_acknowledgments', ['organization_id' => $this->org, 'user_id' => $id, 'notice' => $notice, 'version' => $version, 'acknowledged_at' => $this->ago('-100 days')]);
                }
            }
        }
        // Luis Pascual's guardian said no to the AI companion, so his adviser switched it off; Trisha Mendoza's said yes.
        Db::insert('guardian_consents', ['organization_id' => $this->org, 'student_id' => $this->u['luis'], 'topic' => 'ai', 'answer' => 'no', 'answered_on' => gmdate('Y-m-d', strtotime('-95 days')), 'recorded_by' => $this->u['liza'], 'created_at' => $this->ago('-95 days')]);
        Db::update('users', ['ai_enabled' => 0], ['id' => $this->u['luis']]);
        Db::insert('guardian_consents', ['organization_id' => $this->org, 'student_id' => $this->u['trisha'], 'topic' => 'ai', 'answer' => 'yes', 'answered_on' => gmdate('Y-m-d', strtotime('-96 days')), 'recorded_by' => $this->u['liza'], 'created_at' => $this->ago('-96 days')]);
        Db::update('users', ['ai_enabled' => 1], ['id' => $this->u['trisha']]);
        Tokens::issue($this->u['carmela'], 'invitation', $this->u['admin']);
        Tokens::issue($this->u['rafael'], 'invitation', $this->u['admin']);
    }

    private function structure(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone(self::TZ));
        $startYear = (int) $now->format('n') >= 6 ? (int) $now->format('Y') : (int) $now->format('Y') - 1;
        Db::insert('academic_periods', [
            'organization_id' => $this->org, 'name' => sprintf('School year %d–%d', $startYear - 1, $startYear),
            'starts_on' => ($startYear - 1) . '-06-16', 'ends_on' => $startYear . '-04-10', 'is_current' => 0,
            'created_at' => $this->ago('-400 days'), 'updated_at' => $this->ago('-400 days'),
        ]);
        $this->mod['period'] = Db::insert('academic_periods', [
            'organization_id' => $this->org, 'name' => sprintf('School year %d–%d', $startYear, $startYear + 1),
            'starts_on' => $startYear . '-06-15', 'ends_on' => ($startYear + 1) . '-04-09', 'is_current' => 1,
            'created_at' => $this->ago('-120 days'), 'updated_at' => $this->ago('-120 days'),
        ]);
        foreach (['science' => ['Science', 'SCI'], 'math' => ['Mathematics', 'MATH'], 'english' => ['English', 'ENG'], 'filipino' => ['Filipino', 'FIL']] as $key => [$name, $code]) {
            $this->mod['subject_' . $key] = Db::insert('subjects', [
                'organization_id' => $this->org, 'name' => $name, 'code' => $code,
                'created_at' => $this->ago('-120 days'), 'updated_at' => $this->ago('-120 days'),
            ]);
        }
    }

    private function classes(): void
    {
        $defs = [
            'sci_narra' => ['science', 'Science 9 – Narra', 'Narra', 'ramon', 'narra', 'SCN9QX'],
            'sci_molave' => ['science', 'Science 9 – Molave', 'Molave', 'ramon', 'molave', null],
            'math_narra' => ['math', 'Mathematics 9 – Narra', 'Narra', 'liza', 'narra', 'MTN9KP'],
            'math_molave' => ['math', 'Mathematics 9 – Molave', 'Molave', 'liza', 'molave', null],
            'eng_narra' => ['english', 'English 9 – Narra', 'Narra', 'joy', 'narra', null],
        ];
        foreach ($defs as $key => [$subject, $name, $section, $teacher, $students, $code]) {
            $id = Db::insert('classes', [
                'organization_id' => $this->org,
                'subject_id' => $this->mod['subject_' . $subject],
                'academic_period_id' => $this->mod['period'],
                'name' => $name,
                'section' => $section,
                'section_id' => Sections::forClass($this->org, $this->mod['period'], 'Grade 9', $section),
                'grade_level' => 'Grade 9',
                'description' => null,
                'status' => 'active',
                'join_code' => $code,
                'join_code_enabled' => $code ? 1 : 0,
                'join_code_expires_at' => $code ? $this->at('+10 days', '23:59') : null,
                'created_by' => $this->u['admin'],
                'created_at' => $this->ago('-110 days'),
                'updated_at' => $this->ago('-110 days'),
            ]);
            $this->c[$key] = $id;
            Db::insert('class_memberships', ['class_id' => $id, 'user_id' => $this->u[$teacher], 'role' => 'teacher', 'joined_via' => 'admin', 'joined_at' => $this->ago('-110 days')]);
            foreach ($this->sections[$students] as $student) {
                Db::insert('class_memberships', ['class_id' => $id, 'user_id' => $this->u[$student], 'role' => 'student', 'joined_via' => 'import', 'joined_at' => $this->ago('-105 days')]);
            }
            if ($students === 'narra') {
                Db::insert('class_memberships', ['class_id' => $id, 'user_id' => $this->u['rafael'], 'role' => 'student', 'joined_via' => 'admin', 'joined_at' => $this->ago('-2 days')]);
                Db::insert('class_memberships', ['class_id' => $id, 'user_id' => $this->u['angelica'], 'role' => 'student', 'status' => 'withdrawn', 'joined_via' => 'import', 'joined_at' => $this->ago('-105 days'), 'withdrawn_at' => $this->ago('-20 days')]);
            }
        }
        // Class advisers: Ramon Dizon for Grade 9 Narra, Liza Mercado for Grade 9 Molave.
        Db::exec('UPDATE sections SET adviser_id = :u WHERE organization_id = :o AND name = :n', ['u' => $this->u['ramon'], 'o' => $this->org, 'n' => 'Narra']);
        Db::exec('UPDATE sections SET adviser_id = :u WHERE organization_id = :o AND name = :n', ['u' => $this->u['liza'], 'o' => $this->org, 'n' => 'Molave']);
        $modules = [
            'sci_narra_1' => ['sci_narra', 'Module 1: Respiratory and circulatory systems', 1],
            'sci_narra_2' => ['sci_narra', 'Module 2: Heredity and variation', 2],
            'sci_molave_1' => ['sci_molave', 'Module 1: Respiratory and circulatory systems', 1],
            'sci_molave_2' => ['sci_molave', 'Module 2: Heredity and variation', 2],
            'math_narra_1' => ['math_narra', 'Module 1: Quadratic equations', 1],
            'math_molave_1' => ['math_molave', 'Module 1: Quadratic equations', 1],
            'eng_narra_1' => ['eng_narra', 'Module 1: Reading strategies', 1],
        ];
        foreach ($modules as $key => [$class, $title, $pos]) {
            $this->mod[$key] = Db::insert('modules', ['class_id' => $this->c[$class], 'title' => $title, 'position' => $pos, 'created_at' => $this->ago('-100 days'), 'updated_at' => $this->ago('-100 days')]);
        }
    }

    // ---------------------------------------------------------------- materials & lessons

    private function file(string $name): string
    {
        return (string) file_get_contents(BASE_PATH . '/database/demo-materials/' . $name);
    }

    private function heredityPdf(): string
    {
        $parts = explode("\n=== page ===\n", str_replace("\r\n", "\n", $this->file('heredity-notes.txt')));
        $title = trim((string) strtok($parts[0], "\n"));
        $pages = [];
        foreach ($parts as $i => $part) {
            $lines = explode("\n", trim($part));
            if ($i === 0) {
                array_shift($lines);
            }
            $heading = (string) array_shift($lines);
            $paras = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', implode("\n", $lines)) ?: [])));
            $pages[] = ['heading' => $heading, 'paragraphs' => $paras];
        }
        return SimplePdf::build($title, $pages);
    }

    private function material(string $key, string $class, ?string $module, string $title, string $content, string $filename, string $teacher, ?string $publishedAt, array $extra = []): void
    {
        $stored = Uploads::storeContent($content, 'materials', $filename);
        [$materialId, $versionId] = Materials::createFile([
            'organization_id' => $this->org,
            'class_id' => $this->c[$class],
            'module_id' => $module ? $this->mod[$module] : null,
            'title' => $title,
            'description' => $extra['description'] ?? null,
            'topic' => $extra['topic'] ?? null,
            'ai_enabled' => $extra['ai_enabled'] ?? true,
            'release_at' => $extra['release_at'] ?? null,
        ], $stored, $this->u[$teacher]);
        $created = $publishedAt ?? $this->ago('-1 days');
        Db::update('learning_materials', ['created_at' => $created, 'updated_at' => $created], ['id' => $materialId]);
        Db::update('material_versions', ['created_at' => $created], ['id' => $versionId]);
        if ($publishedAt !== null) {
            Db::update('learning_materials', ['visibility' => 'published', 'published_at' => $publishedAt], ['id' => $materialId]);
        }
        $this->mat[$key] = $materialId;
        $this->versions[] = $versionId;
    }

    private function materials(): void
    {
        $breathing = $this->file('breathing-and-circulation.md');
        $this->material('sci_breathing', 'sci_narra', 'sci_narra_1', 'Breathing and circulation notes', $breathing, 'breathing-and-circulation.md', 'ramon', $this->ago('-12 days'), [
            'topic' => 'Respiratory and circulatory systems', 'description' => 'Read before the breathing-rate lab. Sections 3 and 7 are on the quiz.',
        ]);
        $this->material('sci_heredity', 'sci_narra', 'sci_narra_2', 'Heredity notes', $this->heredityPdf(), 'heredity-notes.pdf', 'ramon', $this->ago('-2 days'), [
            'topic' => 'Heredity', 'description' => 'Four pages: genes and chromosomes, Mendel, Punnett squares, and patterns beyond simple dominance.',
        ]);
        $this->material('sci_review', 'sci_narra', 'sci_narra_2', 'Module 2 review sheet', $this->file('module-2-review.md'), 'module-2-review.md', 'ramon', $this->ago('-1 days'), [
            'topic' => 'Heredity', 'release_at' => $this->at('+3 days', '07:00'),
        ]);
        $this->material('sci_safety', 'sci_narra', 'sci_narra_1', 'Lab safety reminders', $this->file('lab-safety-draft.md'), 'lab-safety-reminders.md', 'ramon', null, [
            'topic' => 'Lab safety', 'ai_enabled' => false,
        ]);
        $this->material('molave_breathing', 'sci_molave', 'sci_molave_1', 'Breathing and circulation notes', $breathing, 'breathing-and-circulation.md', 'ramon', $this->ago('-12 days'), [
            'topic' => 'Respiratory and circulatory systems',
        ]);
        $this->material('math_quadratic', 'math_narra', 'math_narra_1', 'Quadratic equations: four methods', $this->file('quadratic-equations.md'), 'quadratic-equations.md', 'liza', $this->ago('-8 days'), [
            'topic' => 'Quadratic equations', 'description' => 'Worked examples for each method, plus the discriminant.',
        ]);
        $this->material('math_molave_quadratic', 'math_molave', 'math_molave_1', 'Quadratic equations: four methods', $this->file('quadratic-equations.md'), 'quadratic-equations.md', 'liza', $this->ago('-8 days'), [
            'topic' => 'Quadratic equations',
        ]);
        $this->material('eng_inference', 'eng_narra', 'eng_narra_1', 'Making inferences while reading', $this->file('making-inferences.md'), 'making-inferences.md', 'joy', $this->ago('-6 days'), [
            'topic' => 'Making inferences',
        ]);
        $desmos = Materials::createLink([
            'organization_id' => $this->org, 'class_id' => $this->c['math_narra'], 'module_id' => $this->mod['math_narra_1'],
            'title' => 'Graph quadratic functions with Desmos', 'description' => 'Type y = x^2 + 5x + 6 and find where the graph crosses the x-axis.', 'topic' => 'Quadratic equations',
        ], 'https://www.desmos.com/calculator', $this->u['liza']);
        Db::update('learning_materials', ['visibility' => 'published', 'published_at' => $this->ago('-8 days'), 'created_at' => $this->ago('-8 days')], ['id' => $desmos]);
        $this->mat['math_desmos'] = $desmos;
    }

    private function lessons(): void
    {
        $content = [
            'objectives' => "- Describe the structure of the alveoli.\n- Explain how oxygen and carbon dioxide move between the alveoli and the blood.",
            'prior_knowledge' => 'Students know that the body needs oxygen and that air enters through the nose and mouth.',
            'explanation' => "Air reaches the alveoli through the bronchioles. Each alveolus has a wall one cell thick and is wrapped in capillaries. Oxygen moves from high concentration in the alveoli to lower concentration in the blood by **diffusion**; carbon dioxide moves the opposite way. [1]",
            'worked_example' => "Ask: why would gas exchange slow down if the alveolar walls became thicker? Model the answer: the gases would have farther to diffuse, so less oxygen would reach the blood each second.",
            'activities' => "1. Pairs sketch an alveolus and its capillaries and label the direction each gas moves.\n2. Groups compare a sponge and a solid block to discuss why surface area matters.",
            'checks' => "- Which way does carbon dioxide move, and why?\n- Name two features that make gas exchange fast.",
            'exit_ticket' => 'In one sentence, explain why the alveolar wall is only one cell thick.',
            'sources' => [['n' => 1, 'material_id' => $this->mat['sci_breathing'], 'title' => 'Breathing and circulation notes', 'where' => 'Gas exchange in the alveoli']],
        ];
        $json = json_encode($content, JSON_UNESCAPED_UNICODE);
        Db::insert('lessons', [
            'organization_id' => $this->org, 'class_id' => $this->c['sci_narra'], 'module_id' => $this->mod['sci_narra_1'],
            'title' => 'Gas exchange in the alveoli', 'status' => 'published', 'grade_level' => 'Grade 9', 'duration_minutes' => 50,
            'reading_level' => 'Grade 9', 'language' => 'en', 'lesson_format' => 'Discussion and pair work',
            'objectives' => $content['objectives'], 'draft_content' => $json, 'published_content' => $json,
            'source_material_ids' => json_encode([$this->mat['sci_breathing']]), 'ai_assisted' => 0, 'has_unpublished_changes' => 0,
            'created_by' => $this->u['ramon'], 'published_by' => $this->u['ramon'], 'published_at' => $this->ago('-10 days'),
            'created_at' => $this->ago('-11 days'), 'updated_at' => $this->ago('-10 days'),
        ]);
        $draft = [
            'objectives' => "- Use a Punnett square to predict offspring genotypes.\n- Express results as ratios and probabilities.",
            'prior_knowledge' => 'Dominant and recessive alleles; genotype and phenotype.',
            'explanation' => '', 'worked_example' => '', 'activities' => '', 'checks' => '', 'exit_ticket' => '', 'sources' => [],
        ];
        Db::insert('lessons', [
            'organization_id' => $this->org, 'class_id' => $this->c['sci_narra'], 'module_id' => $this->mod['sci_narra_2'],
            'title' => 'Punnett squares in practice', 'status' => 'draft', 'grade_level' => 'Grade 9', 'duration_minutes' => 50,
            'language' => 'en', 'objectives' => $draft['objectives'], 'draft_content' => json_encode($draft, JSON_UNESCAPED_UNICODE),
            'source_material_ids' => json_encode([$this->mat['sci_heredity']]), 'created_by' => $this->u['ramon'],
            'created_at' => $this->ago('-1 days'), 'updated_at' => $this->ago('-1 days'),
        ]);
    }

    private function announcements(): void
    {
        $rows = [
            ['sci_narra', 'ramon', 'Bring a stopwatch on Monday', "For the breathing-rate lab, bring a stopwatch or a phone with a timer. You will work in pairs, so agree with your partner who brings it.", '-3 days'],
            ['math_narra', 'liza', 'Factoring quiz results are out', "Scores and comments are now visible on the quiz page. If a step confuses you, compare it with Method 2 in the notes before Friday's class.", '-20 hours'],
            ['eng_narra', 'joy', 'Library visit on Thursday', 'We will meet at the library for the first 20 minutes of class. Bring your inference journal.', '-2 days'],
        ];
        foreach ($rows as [$class, $author, $title, $body, $when]) {
            Db::insert('announcements', [
                'organization_id' => $this->org, 'class_id' => $this->c[$class], 'author_id' => $this->u[$author],
                'title' => $title, 'body' => $body, 'created_at' => $this->ago($when), 'updated_at' => $this->ago($when),
            ]);
        }
    }

    // ---------------------------------------------------------------- assignments & submissions

    private function makeAssignment(string $key, string $class, string $teacher, array $data, array $questions = []): array
    {
        $this->actAs($teacher);
        $id = Db::insert('assignments', $data + [
            'organization_id' => $this->org,
            'class_id' => $this->c[$class],
            'status' => 'draft',
            'created_by' => $this->u[$teacher],
            'created_at' => $this->ago('-14 days'),
            'updated_at' => $this->ago('-14 days'),
        ]);
        foreach ($questions as $q) {
            [$row, $errors] = Assessments::normaliseQuestion($q);
            if ($errors) {
                throw new \RuntimeException('Seed question invalid: ' . implode(' ', $errors));
            }
            Assessments::addQuestion($id, $row);
        }
        $this->a[$key] = $id;
        return Db::one('SELECT * FROM assignments WHERE id = :id', ['id' => $id]);
    }

    private function publishAt(string $key, string $teacher, string $when): array
    {
        $this->actAs($teacher);
        Assessments::publish($this->a[$key], $this->u[$teacher], false);
        Db::update('assignments', ['published_at' => $when, 'created_at' => $when], ['id' => $this->a[$key]]);
        return Db::one('SELECT * FROM assignments WHERE id = :id', ['id' => $this->a[$key]]);
    }

    private function rubric(array $criteria): string
    {
        $out = [];
        foreach ($criteria as $i => [$title, $points, $desc]) {
            $out[] = ['id' => 'c' . ($i + 1), 'title' => $title, 'points' => $points, 'description' => $desc];
        }
        return (string) json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /** Takes a quiz attempt for a student and back-dates it. */
    private function takeQuiz(array $assignment, string $student, string $submittedAt, array $difficulty, array $wrongAnswers): array
    {
        $studentId = $this->u[$student];
        $this->actAs($student);
        $snapshot = Assessments::snapshotFor((int) $assignment['current_version_id']);
        // The attempt is taken "back then": use an open copy so today's date does not block it;
        // backdate() then recomputes lateness from the real due date.
        $assignment['due_at'] = null;
        $assignment['late_policy'] = 'accepted';
        $sub = Assessments::startAttempt($assignment, $studentId);
        $answers = [];
        foreach ($snapshot['questions'] as $i => $q) {
            $p = max(0.1, min(0.98, $this->ability[$student] - ($difficulty[$i] ?? 0)));
            $correct = mt_rand(1, 1000) / 1000 <= $p;
            $key = $q['answer_key'];
            switch ($q['qtype']) {
                case 'multiple_choice':
                    $answers[$q['id']] = $correct ? $key['correct'] : ($wrongAnswers[$i] ?? 'A');
                    break;
                case 'true_false':
                    $answers[$q['id']] = $correct ? ($key['correct'] ? 'true' : 'false') : ($key['correct'] ? 'false' : 'true');
                    break;
                case 'short_answer':
                    $answers[$q['id']] = $correct ? $key['accepted'][0] : ($wrongAnswers[$i] ?? 'not sure');
                    break;
                case 'essay':
                    $answers[$q['id']] = $wrongAnswers[$i] ?? 'Both parents can carry a hidden recessive allele. If each passes on the recessive allele, the offspring shows the recessive trait.';
                    break;
            }
        }
        Assessments::saveAnswers($sub, $snapshot, $answers);
        $sub = Assessments::submitAttempt($assignment, $sub, $snapshot);
        $this->backdate($sub, $submittedAt, 18);
        return Db::one('SELECT * FROM submissions WHERE id = :id', ['id' => (int) $sub['id']]);
    }

    private function backdate(array $sub, string $submittedAt, int $minutesWorked): void
    {
        $started = gmdate('Y-m-d H:i:s', strtotime($submittedAt . ' UTC') - $minutesWorked * 60);
        $late = Db::value('SELECT due_at FROM assignments WHERE id = :a', ['a' => (int) $sub['assignment_id']]);
        Db::update('submissions', [
            'started_at' => $started,
            'submitted_at' => $submittedAt,
            'created_at' => $started,
            'updated_at' => $submittedAt,
            'is_late' => ($late !== null && $submittedAt > $late) ? 1 : 0,
            'graded_at' => $sub['graded_at'] !== null ? $submittedAt : null,
        ], ['id' => (int) $sub['id']]);
        Db::exec('UPDATE grading_history SET created_at = :t WHERE submission_id = :s', ['t' => $submittedAt, 's' => (int) $sub['id']]);
    }

    private function writtenSubmission(array $assignment, string $student, string $body, string $submittedAt, bool $submit = true): array
    {
        $this->actAs($student);
        $id = Db::insert('submissions', [
            'organization_id' => $this->org,
            'assignment_id' => (int) $assignment['id'],
            'student_id' => $this->u[$student],
            'assignment_version_id' => (int) $assignment['current_version_id'],
            'attempt_no' => 1,
            'status' => 'in_progress',
            'body' => $body,
            'points_possible' => (float) $assignment['points_possible'],
            'started_at' => $submittedAt,
            'created_at' => $submittedAt,
            'updated_at' => $submittedAt,
        ]);
        $sub = Db::one('SELECT * FROM submissions WHERE id = :id', ['id' => $id]);
        if ($submit) {
            $sub = Assessments::submitAssignment($assignment, $sub);
            $this->backdate($sub, $submittedAt, 50);
        }
        return Db::one('SELECT * FROM submissions WHERE id = :id', ['id' => $id]);
    }

    private function gradeAndRelease(array $sub, array $assignment, string $teacher, array $data, ?string $releasedAt): void
    {
        $this->actAs($teacher);
        Assessments::grade($sub, $assignment, $data, $this->u[$teacher]);
        $sub = Db::one('SELECT * FROM submissions WHERE id = :id', ['id' => (int) $sub['id']]);
        if ($releasedAt !== null) {
            Assessments::release($sub, $assignment, $this->u[$teacher]);
            Db::update('submissions', ['released_at' => $releasedAt, 'graded_at' => $releasedAt], ['id' => (int) $sub['id']]);
        } else {
            Db::update('submissions', ['graded_at' => $this->ago('-3 hours')], ['id' => (int) $sub['id']]);
        }
        Db::exec("UPDATE grading_history SET created_at = :t WHERE submission_id = :s AND action IN ('graded','released')", ['t' => $releasedAt ?? $this->ago('-3 hours'), 's' => (int) $sub['id']]);
    }

    private function releaseAll(array $assignment, string $teacher, string $when, array $except = []): void
    {
        $rows = Db::all("SELECT s.* FROM submissions s WHERE s.assignment_id = :a AND s.status = 'submitted' AND s.grading_status = 'graded'", ['a' => (int) $assignment['id']]);
        foreach ($rows as $sub) {
            $studentKey = array_search((int) $sub['student_id'], $this->u, true);
            if (in_array($studentKey, $except, true)) {
                continue;
            }
            $this->actAs($teacher);
            Assessments::release($sub, $assignment, $this->u[$teacher]);
            Db::update('submissions', ['released_at' => $when], ['id' => (int) $sub['id']]);
        }
    }

    /**
     * Sample learning competencies for Science 9, marked as samples (codes starting with DEMO-), so nobody takes them
     * for the MATATAG curriculum's: a real school imports its own list. The demo quizzes' questions and the lessons
     * are tagged with them, so class insights show results by competency.
     */
    private function competencies(): void
    {
        $add = fn (string $code, int $quarter, string $description): int => Db::insert('competencies', [
            'organization_id' => $this->org, 'code' => $code, 'description' => $description,
            'subject_id' => $this->mod['subject_science'], 'grade_level' => 'Grade 9', 'quarter' => $quarter, 'is_sample' => 1,
            'created_at' => $this->ago('-100 days'), 'updated_at' => $this->ago('-100 days'),
        ]);
        $gas = $add('DEMO-SCI9-Q1-01', 1, 'Explain how the respiratory and circulatory systems work together to bring oxygen to the cells and remove carbon dioxide.');
        $heart = $add('DEMO-SCI9-Q1-02', 1, 'Identify the parts of the heart and the blood vessels and describe what each one does.');
        $breathing = $add('DEMO-SCI9-Q1-03', 1, 'Describe how the diaphragm and the ribs change the size of the chest cavity during breathing.');
        $genes = $add('DEMO-SCI9-Q2-01', 2, 'Explain how traits pass from parents to offspring through genes and alleles, including patterns beyond simple dominance.');
        $punnett = $add('DEMO-SCI9-Q2-02', 2, 'Use a Punnett square to predict the offspring of a cross and express the results as ratios.');
        [$in, $params] = Db::in([$this->c['sci_narra'], $this->c['sci_molave']], 'c');
        $topics = ['Gas exchange' => $gas, 'The heart' => $heart, 'Blood vessels' => $heart, 'Breathing' => $breathing,
            'Vocabulary' => $genes, 'Non-Mendelian inheritance' => $genes, 'Punnett squares' => $punnett];
        foreach ($topics as $topic => $id) {
            Db::exec(
                "UPDATE questions q JOIN assignments a ON a.id = q.assignment_id SET q.competency_id = :id WHERE q.topic = :t AND a.class_id IN $in",
                ['id' => $id, 't' => $topic] + $params
            );
        }
        foreach (['Gas exchange in the alveoli' => $gas, 'Punnett squares in practice' => $punnett] as $title => $id) {
            Db::exec(
                "INSERT INTO lesson_competencies (lesson_id, competency_id) SELECT l.id, :id FROM lessons l WHERE l.title = :t AND l.class_id IN $in",
                ['id' => $id, 't' => $title] + $params
            );
        }
    }

    private function assignments(): void
    {
        // Science 9 – Narra: past quiz (graded, released), open lab report, quiz opening today, draft.
        $quizQuestions = [
            ['qtype' => 'multiple_choice', 'prompt' => 'Where does gas exchange between the air and the blood take place?', 'options' => ['Trachea', 'Bronchi', 'Alveoli', 'Larynx'], 'correct' => 'C', 'points' => 1, 'topic' => 'Gas exchange', 'explanation' => 'Gases diffuse across the thin walls of the alveoli into and out of the surrounding capillaries.', 'source_ref' => 'Breathing and circulation notes: Gas exchange in the alveoli'],
            ['qtype' => 'multiple_choice', 'prompt' => 'Which chamber of the heart pumps oxygen-rich blood to the rest of the body?', 'options' => ['Right atrium', 'Right ventricle', 'Left atrium', 'Left ventricle'], 'correct' => 'D', 'points' => 1, 'topic' => 'The heart', 'explanation' => 'The left ventricle has the thickest wall because it pumps blood around the whole body.'],
            ['qtype' => 'true_false', 'prompt' => 'Veins carry blood away from the heart.', 'correct' => 'false', 'points' => 1, 'topic' => 'Blood vessels', 'explanation' => 'Arteries carry blood away from the heart; veins carry it back.'],
            ['qtype' => 'multiple_choice', 'prompt' => 'Why do oxygen and carbon dioxide move across the walls of the alveoli so easily?', 'options' => ['The walls are thick and muscular', 'The walls are one cell thick and moist', 'The alveoli contain valves', 'The air in the alveoli is under high pressure'], 'correct' => 'B', 'points' => 2, 'topic' => 'Gas exchange', 'explanation' => 'Thin, moist walls and a large surface area let the gases diffuse quickly.'],
            ['qtype' => 'true_false', 'prompt' => 'When you inhale, the diaphragm contracts and moves down.', 'correct' => 'true', 'points' => 1, 'topic' => 'Breathing', 'explanation' => 'The contracting diaphragm flattens, making the chest cavity larger so air flows in.'],
            ['qtype' => 'short_answer', 'prompt' => 'Name the tiny blood vessels where substances pass between the blood and the body\'s cells.', 'accepted' => "capillaries\ncapillary", 'points' => 1, 'topic' => 'Blood vessels', 'explanation' => 'Capillaries have walls one cell thick.'],
        ];
        $quizBase = [
            'kind' => 'quiz', 'category' => 'written', 'max_attempts' => 2, 'time_limit_minutes' => 20,
            'late_policy' => 'not_accepted', 'answer_key_release' => 'after_due', 'feedback_release' => 'manual',
            'instructions' => "Answer all six questions. You have 20 minutes once you start, and two attempts. Your best attempt counts.\n\nThe notes on gas exchange and blood vessels will help.",
            'allow_text' => 0, 'allow_files' => 0,
        ];
        $resp = $this->makeAssignment('sci_quiz1', 'sci_narra', 'ramon', $quizBase + [
            'module_id' => $this->mod['sci_narra_1'], 'title' => 'Respiratory and circulatory systems check',
            'objectives' => 'Explain gas exchange; identify the parts of the heart and blood vessels.',
            'opens_at' => $this->at('-6 days', '07:00'), 'due_at' => $this->at('-2 days', '23:59'),
        ], $quizQuestions);
        $resp = $this->publishAt('sci_quiz1', 'ramon', $this->at('-7 days', '15:30'));
        $difficulty = [0.0, 0.1, 0.05, 0.38, 0.0, 0.15];
        $wrong = [0 => 'B', 1 => 'B', 3 => 'D', 5 => 'veins'];
        foreach ($this->sections['narra'] as $i => $student) {
            if (in_array($student, ['carlo', 'paolo'], true)) {
                continue; // missing work
            }
            $this->takeQuiz($resp, $student, $this->at('-' . (3 + $i % 3) . ' days', sprintf('%02d:%02d', 15 + $i % 6, 10 + $i * 3 % 50)), $difficulty, $wrong);
        }
        $this->releaseAll($resp, 'ramon', $this->ago('-1 days'));

        $lab = $this->makeAssignment('sci_lab', 'sci_narra', 'ramon', [
            'kind' => 'assignment', 'module_id' => $this->mod['sci_narra_1'], 'category' => 'performance',
            'title' => 'Lab report: breathing rate before and after exercise',
            'instructions' => "Work with your partner to count breaths per minute at rest, right after two minutes of jumping jacks, and after five minutes of rest.\n\nIn your report:\n\n1. Record your data in a table.\n2. Describe the pattern you see.\n3. Explain the pattern using **both** the respiratory and the circulatory systems.\n4. Write one question you would test next.\n\nYou may type your report below, attach a photo of your table, or both.",
            'objectives' => 'Collect and present data; explain how the two systems respond to exercise.',
            'opens_at' => $this->at('-5 days', '07:00'), 'due_at' => $this->at('+2 days', '23:59'),
            'late_policy' => 'penalty', 'late_penalty_pct' => 10, 'max_attempts' => 1,
            'points_possible' => 10, 'allow_text' => 1, 'allow_files' => 1, 'allow_resubmission' => 0,
            'feedback_release' => 'manual', 'answer_key_release' => 'never',
            'rubric' => $this->rubric([
                ['Data and observations', 4, 'Accurate table with all three readings and a clear description of the pattern.'],
                ['Explanation using both body systems', 4, 'Links breathing rate and heart rate to the muscles\' need for oxygen.'],
                ['Next question', 2, 'A testable question that follows from the results.'],
            ]),
        ]);
        $lab = $this->publishAt('sci_lab', 'ramon', $this->at('-5 days', '07:00'));
        Db::insert('assignment_resources', ['assignment_id' => (int) $lab['id'], 'material_id' => $this->mat['sci_breathing']]);
        $labBodies = [
            'bea' => "Resting: 16 breaths per minute. After jumping jacks: 34. After 5 minutes rest: 19.\n\nOur breathing rate more than doubled after exercise and almost returned to normal after resting. The leg muscles were using more oxygen for cellular respiration and making more carbon dioxide, so we breathed faster to bring in oxygen and remove CO2. My partner's pulse also went up, which means the heart pumped blood faster to carry the oxygen to the muscles.\n\nNext question: does breathing rate return to normal faster for students who exercise every day?",
            'nicole' => "At rest 15, after exercise 31, after rest 18 breaths per minute.\n\nBreathing went up because the muscles needed more energy. The circulatory system moved blood faster too. Next I would test if holding your breath first changes the results.",
            'sofia' => "Rest 17 / exercise 30 / recovery 20.\n\nWe breathed faster after exercising because the body needs more oxygen. Next time I want to measure heart rate also.",
            'miguel' => "Resting 18, after jumping jacks 29, after resting 22. Breathing increases when you exercise.",
            'kyla' => "Data: 16, 33, 18. When we exercise the muscles need more oxygen and produce more carbon dioxide. The diaphragm works faster so more air comes in, and the heart beats faster so the blood carries the oxygen to the muscles quicker. Question: does the type of exercise matter?",
        ];
        $i = 0;
        foreach ($labBodies as $student => $body) {
            $this->writtenSubmission($lab, $student, $body, $this->at('-' . (2 - min(1, $i % 2)) . ' days', sprintf('%02d:%02d', 18 + $i, 5 + $i * 7)));
            $i++;
        }
        $this->writtenSubmission($lab, 'andrea', "Resting: 15 breaths per minute\nAfter jumping jacks: 32\nAfter 5 minutes rest: 17\n\nThe pattern: breathing rate doubled after exercise and went almost back to normal after resting.", $this->ago('-20 hours'), false);
        $this->writtenSubmission($lab, 'joshua', 'Rest 18, exercise 30', $this->ago('-1 days'), false);

        $heredityQuestions = [
            ['qtype' => 'multiple_choice', 'prompt' => 'Two heterozygous tall pea plants (Tt) are crossed. What is the probability that an offspring is short?', 'options' => ['0', '1 in 4', '1 in 2', '3 in 4'], 'correct' => 'B', 'points' => 1, 'topic' => 'Punnett squares', 'explanation' => 'The cross gives TT, Tt, Tt and tt; only tt is short, so 1 in 4.'],
            ['qtype' => 'true_false', 'prompt' => 'A person with type AB blood shows both the A and the B allele because they are codominant.', 'correct' => 'true', 'points' => 1, 'topic' => 'Non-Mendelian inheritance', 'explanation' => 'In codominance both alleles are fully expressed.'],
            ['qtype' => 'multiple_choice', 'prompt' => 'What do we call the trait that an organism shows?', 'options' => ['Genotype', 'Phenotype', 'Allele', 'Gamete'], 'correct' => 'B', 'points' => 1, 'topic' => 'Vocabulary', 'explanation' => 'The phenotype is the observable trait; the genotype is the allele combination.'],
            ['qtype' => 'short_answer', 'prompt' => 'What is the term for different versions of the same gene?', 'accepted' => "alleles\nallele", 'points' => 1, 'topic' => 'Vocabulary', 'explanation' => 'Different versions of a gene are alleles.'],
            ['qtype' => 'essay', 'prompt' => 'In three or four sentences, explain how two tall parent plants can have a short offspring.', 'points' => 4, 'topic' => 'Punnett squares'],
        ];
        $heredity = $this->makeAssignment('sci_quiz2', 'sci_narra', 'ramon', [
            'kind' => 'quiz', 'module_id' => $this->mod['sci_narra_2'], 'category' => 'written',
            'title' => 'Heredity basics', 'instructions' => 'Five questions on the heredity notes. The last question is written and will be read by your teacher. 20 minutes, one attempt.',
            'opens_at' => $this->ago('-2 hours'), 'due_at' => $this->at('+6 days', '23:59'),
            'max_attempts' => 1, 'time_limit_minutes' => 20, 'late_policy' => 'accepted', 'answer_key_release' => 'after_due', 'feedback_release' => 'manual',
            'allow_text' => 0, 'allow_files' => 0,
        ], $heredityQuestions);
        $heredity = $this->publishAt('sci_quiz2', 'ramon', $this->at('-1 days', '16:00'));
        Db::insert('assignment_resources', ['assignment_id' => (int) $heredity['id'], 'material_id' => $this->mat['sci_heredity']]);
        foreach (['bea', 'kyla'] as $j => $student) {
            $this->takeQuiz($heredity, $student, $this->ago('-' . (40 + $j * 35) . ' minutes'), [0.0, 0.0, 0.0, 0.0, 0.0], [4 => $student === 'bea'
                ? 'Each tall parent can be heterozygous (Tt), carrying a hidden recessive allele for shortness. During gamete formation the alleles separate, so each parent can pass on t. If both pass on t, the offspring is tt and short. A Punnett square shows this happens about 1 in 4 times.'
                : 'The parents can both be Tt. Then the child can get t from each parent and be short.']);
        }

        $this->makeAssignment('sci_draft', 'sci_narra', 'ramon', [
            'kind' => 'assignment', 'module_id' => $this->mod['sci_narra_1'], 'category' => 'performance',
            'title' => 'Concept map: from air to cell', 'instructions' => 'Draw a concept map that follows one oxygen molecule from the air to a muscle cell.',
            'due_at' => $this->at('+9 days', '23:59'), 'points_possible' => 10, 'allow_text' => 0, 'allow_files' => 1,
        ]);

        // Science 9 – Molave: the same past quiz.
        $molaveQuiz = $this->makeAssignment('molave_quiz1', 'sci_molave', 'ramon', $quizBase + [
            'module_id' => $this->mod['sci_molave_1'], 'title' => 'Respiratory and circulatory systems check',
            'opens_at' => $this->at('-6 days', '07:00'), 'due_at' => $this->at('-2 days', '23:59'),
        ], $quizQuestions);
        $molaveQuiz = $this->publishAt('molave_quiz1', 'ramon', $this->at('-7 days', '15:40'));
        foreach ($this->sections['molave'] as $i => $student) {
            if ($student === 'daniel') {
                continue;
            }
            $this->takeQuiz($molaveQuiz, $student, $this->at('-' . (3 + $i % 2) . ' days', '16:' . sprintf('%02d', 10 + $i * 5)), $difficulty, $wrong);
        }

        // Mathematics 9 – Narra: graded factoring quiz (mostly released) and an open explanation task.
        $mathQuestions = [
            ['qtype' => 'multiple_choice', 'prompt' => 'Which equation is quadratic?', 'options' => ['3x + 2 = 0', 'x^2 - 4x + 3 = 0', 'x^3 = 8', '2/x = 5'], 'correct' => 'B', 'points' => 1, 'topic' => 'Standard form', 'explanation' => 'The highest power of x is 2.'],
            ['qtype' => 'multiple_choice', 'prompt' => 'Solve by factoring: x^2 + 5x + 6 = 0', 'options' => ['x = 2 or x = 3', 'x = -2 or x = -3', 'x = -1 or x = -6', 'x = 1 or x = 6'], 'correct' => 'B', 'points' => 2, 'topic' => 'Factoring', 'explanation' => '(x + 2)(x + 3) = 0, so x = -2 or x = -3.'],
            ['qtype' => 'multiple_choice', 'prompt' => 'Solve by factoring: x^2 - 7x + 10 = 0', 'options' => ['x = 2 or x = 5', 'x = -2 or x = -5', 'x = 1 or x = 10', 'x = -1 or x = -10'], 'correct' => 'A', 'points' => 2, 'topic' => 'Factoring', 'explanation' => '(x - 2)(x - 5) = 0.'],
            ['qtype' => 'true_false', 'prompt' => 'If (x - 4)(x + 1) = 0, then x = 4 or x = -1.', 'correct' => 'true', 'points' => 1, 'topic' => 'Zero product property', 'explanation' => 'Set each factor equal to zero.'],
            ['qtype' => 'short_answer', 'prompt' => 'What is the positive solution of x^2 - 49 = 0?', 'accepted' => "7\nx = 7\nx=7", 'points' => 1, 'topic' => 'Extracting square roots', 'explanation' => 'x^2 = 49, so x = 7 or x = -7.'],
        ];
        $mathQuiz = $this->makeAssignment('math_quiz1', 'math_narra', 'liza', [
            'kind' => 'quiz', 'module_id' => $this->mod['math_narra_1'], 'category' => 'written',
            'title' => 'Solving quadratic equations by factoring', 'instructions' => 'Five questions. Show your work on scratch paper; only the final answer is recorded here.',
            'opens_at' => $this->at('-9 days', '07:00'), 'due_at' => $this->at('-1 days', '21:00'),
            'max_attempts' => 1, 'time_limit_minutes' => 25, 'late_policy' => 'not_accepted', 'answer_key_release' => 'after_due', 'feedback_release' => 'manual',
            'allow_text' => 0, 'allow_files' => 0,
        ], $mathQuestions);
        $mathQuiz = $this->publishAt('math_quiz1', 'liza', $this->at('-10 days', '14:00'));
        foreach ($this->sections['narra'] as $i => $student) {
            if ($student === 'carlo') {
                continue;
            }
            $this->takeQuiz($mathQuiz, $student, $this->at('-' . (2 + $i % 4) . ' days', sprintf('%02d:%02d', 19 + $i % 3, 5 + $i * 4 % 50)), [0.0, 0.12, 0.2, 0.05, 0.1], [1 => 'A', 2 => 'B', 4 => '49']);
        }
        $this->releaseAll($mathQuiz, 'liza', $this->ago('-20 hours'), ['enzo', 'gabriel']);

        $method = $this->makeAssignment('math_method', 'math_narra', 'liza', [
            'kind' => 'assignment', 'module_id' => $this->mod['math_narra_1'], 'category' => 'performance',
            'title' => 'Choosing a method: explain your reasoning',
            'instructions' => 'Solve each equation using the method you think is best, and explain in one or two sentences why you chose it.' . "\n\n"
                . '1. $x^2 - 81 = 0$' . "\n" . '2. $x^2 + 3x - 10 = 0$' . "\n" . '3. $2x^2 + 3x - 4 = 0$',
            'opens_at' => $this->at('-2 days', '07:00'), 'due_at' => $this->at('+4 days', '23:59'),
            'points_possible' => 10, 'allow_text' => 1, 'allow_files' => 1, 'late_policy' => 'accepted', 'feedback_release' => 'manual', 'answer_key_release' => 'never',
            'rubric' => $this->rubric([
                ['Correct solutions', 6, 'Two points per equation, including both roots where they exist.'],
                ['Reasoning', 4, 'Explains why the method fits each equation.'],
            ]),
        ]);
        $this->publishAt('math_method', 'liza', $this->at('-2 days', '07:00'));

        $mathMolave = $this->makeAssignment('math_molave_quiz', 'math_molave', 'liza', [
            'kind' => 'quiz', 'module_id' => $this->mod['math_molave_1'], 'category' => 'written',
            'title' => 'Solving quadratic equations by factoring', 'instructions' => 'Five questions on factoring.',
            'opens_at' => $this->at('+1 days', '07:00'), 'due_at' => $this->at('+5 days', '21:00'),
            'max_attempts' => 1, 'time_limit_minutes' => 25, 'answer_key_release' => 'after_due', 'allow_text' => 0, 'allow_files' => 0,
        ], $mathQuestions);
        $this->publishAt('math_molave_quiz', 'liza', $this->ago('-2 hours'));

        // English 9 – Narra: graded reading response and a journal due tomorrow.
        $response = $this->makeAssignment('eng_response', 'eng_narra', 'joy', [
            'kind' => 'assignment', 'module_id' => $this->mod['eng_narra_1'], 'category' => 'written',
            'title' => 'Reading response: The jeepney stop',
            'instructions' => 'Read the practice passage in "Making inferences while reading". What can you infer about Mara? Support your inference with at least two clues from the text.',
            'opens_at' => $this->at('-9 days', '07:00'), 'due_at' => $this->at('-5 days', '23:59'),
            'points_possible' => 10, 'allow_text' => 1, 'allow_files' => 0, 'late_policy' => 'accepted', 'feedback_release' => 'manual', 'answer_key_release' => 'never',
            'rubric' => $this->rubric([
                ['Inference', 4, 'States a reasonable inference that goes beyond what the text says directly.'],
                ['Evidence', 4, 'Uses at least two specific clues from the passage.'],
                ['Clarity', 2, 'Clear, complete sentences.'],
            ]),
        ]);
        $response = $this->publishAt('eng_response', 'joy', $this->at('-9 days', '07:00'));
        $feedbackBank = [
            [10, [4, 4, 2], 'Excellent. Your inference is well supported, and you explained how each clue connects to it.'],
            [9, [4, 3, 2], 'Strong inference. Add one more sentence explaining how the second clue supports your idea.'],
            [8, [3, 3, 2], 'Good work. You found the right clues; push your inference a step further than the text states.'],
            [7, [3, 2, 2], 'A reasonable inference. Quote the clues directly so your evidence is easier to follow.'],
            [6, [2, 2, 2], 'You summarised the passage well. Next time, say what the clues suggest that the writer does not state.'],
        ];
        $responses = [
            'andrea' => "I infer that Mara is worried about being late for something important. She has been waiting at the stop \"for the third time that week\", and she keeps \"glancing at her wristwatch and tapping her foot\". People do that when time is running out. She still lets the older women board first, so she is considerate even when she is in a hurry.",
            'bea' => 'Mara is anxious about time, probably about being late for school or work. The clues are that she checks her watch again and again and taps her foot, and that this is the third time this week she has waited. Her umbrella on a clear day might show she is always prepared, or that the weather has been unpredictable.',
        ];
        foreach ($this->sections['narra'] as $i => $student) {
            if ($student === 'paolo') {
                continue;
            }
            $body = $responses[$student] ?? 'Mara is worried about being late because she keeps looking at her watch and tapping her foot. She has waited many times this week.';
            $sub = $this->writtenSubmission($response, $student, $body, $this->at('-' . (6 + $i % 2) . ' days', sprintf('%02d:%02d', 19 + $i % 4, 12 + $i * 3 % 45)));
            $band = $student === 'andrea' ? $feedbackBank[1] : ($student === 'bea' ? $feedbackBank[0] : $feedbackBank[min(4, max(0, (int) round((0.95 - $this->ability[$student]) * 10)))]);
            $this->gradeAndRelease($sub, $response, 'joy', [
                'rubric' => ['c1' => $band[1][0], 'c2' => $band[1][1], 'c3' => $band[1][2]],
                'feedback' => $band[2],
            ], $this->ago('-4 days'));
        }

        $journal = $this->makeAssignment('eng_journal', 'eng_narra', 'joy', [
            'kind' => 'assignment', 'module_id' => $this->mod['eng_narra_1'], 'category' => 'performance',
            'title' => 'Inference journal: three clues',
            'instructions' => 'Choose a short story or a news article you read this week. Write down three clues from it and the inference each clue supports. Use the clue + what I know = inference formula.',
            'opens_at' => $this->at('-7 days', '07:00'), 'due_at' => $this->at('+1 days', '21:00'),
            'points_possible' => 10, 'allow_text' => 1, 'allow_files' => 0, 'late_policy' => 'penalty', 'late_penalty_pct' => 10,
            'feedback_release' => 'manual', 'answer_key_release' => 'never',
            'rubric' => $this->rubric([
                ['Three clue–inference pairs', 6, 'Each clue is quoted and leads to a reasonable inference.'],
                ['Use of the formula', 2, 'Shows the clue + what I know = inference reasoning.'],
                ['Clarity', 2, 'Clear, complete sentences.'],
            ]),
        ]);
        $journal = $this->publishAt('eng_journal', 'joy', $this->at('-7 days', '07:00'));
        foreach (['bea', 'nicole', 'patricia', 'sofia'] as $k => $student) {
            $this->writtenSubmission($journal, $student, 'Story: "The Mango Tree". Clue 1: the grandmother waters the tree every evening. Inference: the tree is important to her family history. Clue 2: ... Clue 3: ...', $this->ago('-' . (5 + $k * 3) . ' hours'));
        }

        $this->paperWork();
    }

    /**
     * Science 9 – Narra on paper: a test taken in class three days ago (scores entered; Joshua was absent,
     * Miguel is excused, Kyla's score is not released yet) and a performance task coming up in class.
     */
    private function paperWork(): void
    {
        $questions = [
            ['qtype' => 'multiple_choice', 'prompt' => 'Which structure carries air from the larynx to the bronchi?', 'options' => ['Trachea', 'Alveoli', 'Diaphragm', 'Capillaries'], 'correct' => 'A', 'points' => 2, 'topic' => 'Breathing'],
            ['qtype' => 'multiple_choice', 'prompt' => 'Which blood vessels carry blood back to the heart?', 'options' => ['Arteries', 'Veins', 'Capillaries', 'Alveoli'], 'correct' => 'B', 'points' => 2, 'topic' => 'Blood vessels'],
            ['qtype' => 'multiple_choice', 'prompt' => 'What does the right ventricle pump blood to?', 'options' => ['The brain', 'The lungs', 'The liver', 'The arms and legs'], 'correct' => 'B', 'points' => 2, 'topic' => 'The heart'],
            ['qtype' => 'true_false', 'prompt' => 'The diaphragm relaxes and moves up when you exhale.', 'correct' => 'true', 'points' => 2, 'topic' => 'Breathing'],
            ['qtype' => 'true_false', 'prompt' => 'Capillary walls are many cells thick.', 'correct' => 'false', 'points' => 2, 'topic' => 'Blood vessels'],
            ['qtype' => 'short_answer', 'prompt' => 'Name the gas that the blood picks up in the alveoli.', 'accepted' => 'oxygen', 'points' => 2, 'topic' => 'Gas exchange'],
            ['qtype' => 'essay', 'prompt' => 'Explain how the respiratory and circulatory systems work together to deliver oxygen to a muscle cell.', 'points' => 8, 'topic' => 'Gas exchange'],
        ];
        $test = $this->makeAssignment('sci_paper_test', 'sci_narra', 'ramon', [
            'kind' => 'paper', 'module_id' => $this->mod['sci_narra_1'], 'category' => 'periodic',
            'title' => 'Short test on paper: breathing and circulation',
            'instructions' => 'Written in class. Answer all questions on the test paper; the last question needs a short paragraph.',
            'due_at' => $this->at('-3 days', '10:00'), 'late_policy' => 'accepted', 'allow_text' => 0, 'allow_files' => 0,
            'feedback_release' => 'manual', 'answer_key_release' => 'never',
        ], $questions);
        $test = $this->publishAt('sci_paper_test', 'ramon', $this->at('-10 days', '07:00'));
        $scores = [];
        foreach ($this->sections['narra'] as $i => $student) {
            if (!in_array($student, ['joshua', 'miguel'], true)) {
                // Fixed offsets rather than mt_rand, so the rest of the seed stays as it was.
                $scores[$this->u[$student]] = (string) max(4, min(20, round(20 * $this->ability[$student]) + ($i % 5) - 2));
            }
        }
        $this->actAs('ramon');
        PaperScores::save($this->users['ramon'], $test, $scores, [$this->u['joshua']], [$this->u['miguel']], false);
        foreach (Db::all("SELECT * FROM submissions WHERE assignment_id = :a AND source = 'paper'", ['a' => (int) $test['id']]) as $sub) {
            Db::update('submissions', ['graded_at' => $this->at('-2 days', '16:00')], ['id' => (int) $sub['id']]);
            if ((int) $sub['student_id'] !== $this->u['kyla'] && (int) $sub['student_id'] !== $this->u['miguel']) {
                Assessments::release($sub, $test, $this->u['ramon']);
                Db::update('submissions', ['released_at' => $this->at('-1 days', '17:00')], ['id' => (int) $sub['id']]);
            }
        }

        $this->makeAssignment('sci_paper_task', 'sci_narra', 'ramon', [
            'kind' => 'paper', 'module_id' => $this->mod['sci_narra_1'], 'category' => 'performance',
            'title' => 'Performance task in class: model of the lungs',
            'instructions' => "In groups, you will build a working model of the lungs from a plastic bottle, balloons and a rubber sheet, then explain how it shows breathing in and out.\n\nBring: one 1.5-litre plastic bottle and two balloons.",
            'due_at' => $this->at('+5 days', '13:00'), 'late_policy' => 'accepted', 'allow_text' => 0, 'allow_files' => 0,
            'points_possible' => 20, 'feedback_release' => 'manual', 'answer_key_release' => 'never',
            'rubric' => $this->rubric([
                ['Working model', 8, 'The model shows the lungs filling and emptying as the sheet moves.'],
                ['Explanation', 8, 'Explains how the diaphragm changes the space in the chest.'],
                ['Teamwork and care', 4, 'Everyone takes part; materials are handled safely.'],
            ]),
        ]);
        $this->publishAt('sci_paper_task', 'ramon', $this->at('-2 days', '07:00'));
    }

    // ---------------------------------------------------------------- activity, notifications, audit

    private function activity(): void
    {
        $andrea = $this->u['andrea'];
        $views = [
            ['material', $this->mat['sci_breathing'], 'sci_narra', '-1 days -3 hours', 4],
            ['material', $this->mat['sci_heredity'], 'sci_narra', '-26 hours', 1],
            ['material', $this->mat['math_quadratic'], 'math_narra', '-3 days', 3],
            ['material', $this->mat['eng_inference'], 'eng_narra', '-5 days', 2],
        ];
        foreach ($views as [$type, $id, $class, $when, $count]) {
            Db::insert('content_views', ['user_id' => $andrea, 'content_type' => $type, 'content_id' => $id, 'class_id' => $this->c[$class], 'view_count' => $count, 'first_viewed_at' => $this->ago('-6 days'), 'last_viewed_at' => $this->ago($when)]);
        }
        Db::insert('study_sessions', [
            'organization_id' => $this->org, 'user_id' => $andrea, 'class_id' => $this->c['sci_narra'],
            'title' => 'Review Punnett squares before the heredity quiz', 'notes' => 'Page 3 of the heredity notes.',
            'starts_at' => $this->at('+1 days', '19:00'), 'duration_minutes' => 30, 'source' => 'manual', 'created_at' => $this->ago('-1 days'),
        ]);
        Db::insert('privacy_requests', [
            'organization_id' => $this->org, 'user_id' => $this->u['nicole'], 'request_type' => 'export',
            'details' => 'I would like a copy of my information for my records.', 'status' => 'submitted',
            'created_at' => $this->ago('-1 days'), 'updated_at' => $this->ago('-1 days'),
        ]);
    }

    /**
     * Companion history produced by the real pipeline in demo mode (extractive answers from the
     * seeded materials, no AI model, no tokens), so the student history, usage counts and the
     * reported-answer screen show genuine output rather than invented numbers.
     */
    private function companion(): void
    {
        Providers::fake(new DemoProvider());
        $chats = [
            ['andrea', 'sci_narra', 'ask_materials', 'How do the respiratory and circulatory systems work together when I exercise?', '-1 days -3 hours'],
            ['andrea', 'sci_narra', 'explain', 'What is the difference between a dominant and a recessive allele?', '-5 hours'],
            ['bea', 'math_narra', 'explain', 'How do I solve x^2 - 5x + 6 = 0 by factoring?', '-2 days -1 hours'],
        ];
        $answers = [];
        foreach ($chats as [$student, $class, $mode, $question, $when]) {
            $user = Db::one(
                'SELECT u.*, o.name AS org_name, o.timezone AS org_timezone FROM users u JOIN organizations o ON o.id = u.organization_id WHERE u.id = :u',
                ['u' => $this->u[$student]]
            );
            Auth::actingAs($user);
            $id = Db::insert('ai_conversations', [
                'organization_id' => $this->org, 'user_id' => $this->u[$student], 'class_id' => $this->c[$class],
                'mode' => $mode, 'title' => 'New session', 'language' => 'en', 'last_message_at' => Db::now(),
                'created_at' => Db::now(), 'updated_at' => Db::now(),
            ]);
            $conversation = (array) Db::one('SELECT * FROM ai_conversations WHERE id = :id', ['id' => $id]);
            Companion::turn($user, $conversation, $question, static function (array $event): void {
            }, static fn (): bool => false);
            // Move the exchange to a believable time; content and counts are left exactly as produced.
            $at = $this->ago($when);
            Db::exec('UPDATE ai_messages SET created_at = :t WHERE conversation_id = :c', ['t' => $at, 'c' => $id]);
            Db::exec('UPDATE ai_conversations SET created_at = :t1, updated_at = :t2, last_message_at = :t3 WHERE id = :c', ['t1' => $at, 't2' => $at, 't3' => $at, 'c' => $id]);
            Db::exec('UPDATE ai_usage_events SET created_at = :t WHERE user_id = :u AND created_at >= :since', ['t' => $at, 'u' => $this->u[$student], 'since' => gmdate('Y-m-d H:i:s', time() - 600)]);
            $answers[$student][] = (int) Db::value("SELECT id FROM ai_messages WHERE conversation_id = :c AND role = 'assistant' ORDER BY id DESC LIMIT 1", ['c' => $id]);
        }
        Providers::fake(null);
        Auth::actingAs(null);

        $reported = $answers['bea'][0] ?? 0;
        if ($reported) {
            Db::insert('ai_reports', [
                'organization_id' => $this->org, 'message_id' => $reported, 'reporter_id' => $this->u['bea'], 'reason' => 'not_helpful',
                'note' => 'It quoted the class notes but did not show the steps for my own equation.',
                'answer_excerpt' => mb_substr((string) Db::value('SELECT content FROM ai_messages WHERE id = :m', ['m' => $reported]), 0, 4000),
                'status' => 'open', 'created_at' => $this->ago('-2 days -50 minutes'),
            ]);
        }
    }

    private function notifications(): void
    {
        $note = function (string $user, string $type, string $title, ?string $body, string $link, string $when, bool $read = false): void {
            Db::insert('notifications', [
                'organization_id' => $this->org, 'user_id' => $this->u[$user], 'type' => $type, 'title' => $title, 'body' => $body,
                'link' => $link, 'read_at' => $read ? $this->ago($when . ' +1 hour') : null, 'created_at' => $this->ago($when),
            ]);
        };
        $note('andrea', 'assignment', 'New quiz: Heredity basics', 'Science 9 – Narra. Due in 6 days.', '/student/assignments/' . $this->a['sci_quiz2'], '-20 hours');
        $note('andrea', 'feedback', 'Feedback released: Respiratory and circulatory systems check', 'Your teacher has returned your grade and comments.', '/student/assignments/' . $this->a['sci_quiz1'], '-1 days');
        $note('andrea', 'material', 'New material: Heredity notes', 'Science 9 – Narra', '/student/classes/' . $this->c['sci_narra'] . '?tab=materials', '-2 days');
        $note('andrea', 'feedback', 'Feedback released: Solving quadratic equations by factoring', 'Your teacher has returned your grade and comments.', '/student/assignments/' . $this->a['math_quiz1'], '-20 hours', true);
        $note('andrea', 'feedback', 'Feedback released: Reading response: The jeepney stop', 'Your teacher has returned your grade and comments.', '/student/assignments/' . $this->a['eng_response'], '-4 days', true);
        $note('ramon', 'submission', 'New work to review: Lab report: breathing rate before and after exercise', '5 submissions so far.', '/teacher/assignments/' . $this->a['sci_lab'] . '/submissions', '-6 hours');
        $note('ramon', 'submission', 'New work to review: Heredity basics', 'Written answers need your marking.', '/teacher/assignments/' . $this->a['sci_quiz2'] . '/submissions', '-3 hours');
        $note('admin', 'privacy', 'New privacy request', 'A student asked for a copy of their data.', '/admin/privacy', '-1 days');
        $note('admin', 'privacy', 'A companion answer was reported', 'Reason: not helpful', '/admin/privacy#reports', '-2 days -50 minutes');
    }

    /** Feedback about Sibol from a few students and teachers, one in each status (fictional). */
    /**
     * Key words of Science 9 – Narra questions the class materials did not cover, so "What your materials don't
     * cover" in Insights has something to show. Two topics reach the five-student minimum; one does not.
     */
    private function materialGaps(): void
    {
        $narra = $this->sections['narra'];
        $asked = [
            ['punnett probability', array_slice($narra, 0, 6), ['-2 days', '-3 days', '-5 days', '-6 days', '-9 days', '-12 days']],
            ['punnett probability dihybrid', array_slice($narra, 0, 2), ['-1 days', '-4 days']],
            ['blood types inheritance', array_slice($narra, 3, 5), ['-1 days', '-2 days', '-7 days', '-8 days', '-15 days']],
            ['mitosis meiosis', array_slice($narra, 8, 2), ['-3 days', '-10 days']],
        ];
        foreach ($asked as [$keywords, $students, $when]) {
            foreach ($students as $i => $student) {
                Db::insert('material_gaps', [
                    'organization_id' => $this->org, 'class_id' => $this->c['sci_narra'], 'student_id' => $this->u[$student],
                    'keywords' => $keywords, 'created_at' => $this->ago($when[$i] . ' -' . (2 + $i) . ' hours'),
                ]);
            }
        }
    }

    private function feedback(): void
    {
        $items = [
            ['andrea', 'confusing', 'I could not find the correct answers after I submitted the quiz. Are they hidden until later?', '/student/assignments/' . $this->a['sci_quiz1'], 'fixed',
                'Yes. Your teacher releases the answers after the due date, and they then appear on the same page as your receipt.', '-12 days'],
            ['bea', 'problem', 'The class page took a long time to open on mobile data this morning.', '/student/classes/' . $this->c['math_narra'], 'looking', null, '-3 days'],
            ['joshua', 'idea', 'Could the flashcards show a picture from the module?', '/student/learning', 'wont_fix', 'Not during the pilot, but we have noted it for later.', '-9 days'],
            ['ramon', 'idea', 'It would help to print the score grid as a class record sheet.', '/teacher/assignments/' . $this->a['sci_paper_test'] . '/scores', 'new', null, '-1 days'],
            ['liza', 'confusing', 'I was not sure whether held scores count in the gradebook before I release them.', '/teacher/gradebook', 'new', null, '-6 hours'],
        ];
        foreach ($items as [$who, $category, $body, $path, $status, $reply, $when]) {
            $id = Db::insert('site_feedback', [
                'organization_id' => $this->org, 'user_id' => $this->u[$who], 'user_role' => $this->users[$who]['role'],
                'category' => $category, 'body' => $body, 'page_path' => $path, 'status' => $status, 'reply' => $reply,
                'replied_by' => $reply !== null ? $this->u['admin'] : null, 'replied_at' => $reply !== null ? $this->ago($when . ' +1 day') : null,
                'created_at' => $this->ago($when), 'updated_at' => $this->ago($reply !== null ? $when . ' +1 day' : $when),
            ]);
            if ($reply !== null) {
                Db::insert('notifications', [
                    'organization_id' => $this->org, 'user_id' => $this->u[$who], 'type' => 'site_feedback', 'title' => 'Reply to your feedback',
                    'body' => $reply, 'link' => '/feedback#feedback-' . $id, 'read_at' => $this->ago($when . ' +2 days'), 'created_at' => $this->ago($when . ' +1 day'),
                ]);
            }
        }
    }

    private function auditTrail(): void
    {
        $rows = [
            ['admin', 'class.created', 'class', $this->c['sci_narra'], ['name' => 'Science 9 – Narra'], '-110 days'],
            ['admin', 'users.imported', null, null, ['created' => 18, 'skipped' => 0], '-105 days'],
            ['admin', 'settings.grading_updated', null, null, ['model' => 'weighted'], '-100 days'],
            ['admin', 'user.invited', 'user', $this->u['rafael'], ['role' => 'student'], '-2 days'],
            ['admin', 'user.invited', 'user', $this->u['carmela'], ['role' => 'teacher'], '-2 days'],
            ['admin', 'user.deactivated', 'user', $this->u['angelica'], ['reason' => 'Transferred'], '-20 days'],
            ['ramon', 'material.published', 'material', $this->mat['sci_heredity'], [], '-2 days'],
            ['ramon', 'assignment.published', 'assignment', $this->a['sci_quiz2'], [], '-1 days'],
            ['liza', 'submission.feedback_released', 'assignment', $this->a['math_quiz1'], ['count' => 9], '-20 hours'],
            ['joy', 'gradebook.exported', 'class', $this->c['eng_narra'], ['format' => 'csv'], '-3 days'],
        ];
        foreach ($rows as [$actor, $action, $type, $target, $meta, $when]) {
            Db::insert('audit_logs', [
                'organization_id' => $this->org, 'actor_id' => $this->u[$actor], 'actor_role' => $this->users[$actor]['role'],
                'action' => $action, 'target_type' => $type, 'target_id' => $target,
                'metadata' => $meta ? json_encode($meta) : null, 'created_at' => $this->ago($when),
            ]);
        }
    }
}
