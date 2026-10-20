<?php

declare(strict_types=1);

namespace Sibol\Services;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Policies\Access;

/**
 * A student's private review queue. Questions they missed in practice, and in quizzes whose answers they can see,
 * come back on a schedule: later each time they are answered right, sooner again when missed. Choice and typed
 * answers are checked by Sibol; only flashcards are self-rated. Reviews are never graded and never shown to
 * teachers. Everything that depends on the time takes "now", so tests can move the clock.
 */
final class SpacedReview
{
    /** Days until the next review after the first, second, third... right answer in a row. */
    public const INTERVALS = [1, 3, 7, 14, 30];

    /**
     * Where an item goes after an answer. Right: one interval further; right at the last interval, it is learned and
     * leaves the queue. Wrong: back one interval, not to the start, and it returns tomorrow.
     * @param string $today the school's local date, Y-m-d
     * @return array{step:int,due_on:?string,done:bool}
     */
    public static function next(int $step, bool $right, string $today): array
    {
        if (!$right) {
            return ['step' => max(0, $step - 1), 'due_on' => self::addDays($today, 1), 'done' => false];
        }
        if ($step >= count(self::INTERVALS)) {
            return ['step' => $step, 'due_on' => null, 'done' => true];
        }
        return ['step' => $step + 1, 'due_on' => self::addDays($today, self::INTERVALS[$step]), 'done' => false];
    }

    /** The school's local date at a moment. */
    public static function today(int $orgId, DateTimeImmutable $now): string
    {
        return $now->setTimezone(GradingPeriods::zone($orgId))->format('Y-m-d');
    }

    /** The school's daily number of reviews per student; 0 means no limit. */
    public static function dailyCap(int $orgId): int
    {
        return max(0, (int) Settings::get($orgId, 'review.daily_cap', 20));
    }

    /**
     * What the student can review now. Normally: reviews that are due, the longest waiting first, then new items,
     * up to the daily number less what was reviewed today. With $classId ("review everything before my quiz"):
     * every item of that class not learned yet, due or not, without the daily number.
     * @return list<array<string,mixed>>
     */
    public static function queue(array $student, DateTimeImmutable $now, ?int $classId = null): array
    {
        $today = self::today((int) $student['organization_id'], $now);
        $params = ['u' => (int) $student['id']];
        $where = self::visibleSql($student);
        if ($classId !== null) {
            $where .= ' AND r.class_id = :c';
            $params['c'] = $classId;
        } else {
            $where .= ' AND r.due_on <= :d';
            $params['d'] = $today;
        }
        $rows = Db::all(
            "SELECT r.*, c.name AS class_name FROM review_items r JOIN classes c ON c.id = r.class_id
             WHERE $where ORDER BY (r.reviews = 0), r.due_on, r.id",
            $params
        );
        $rows = self::releasedOnly($student, $rows, $now);
        if ($classId === null) {
            $cap = self::dailyCap((int) $student['organization_id']);
            if ($cap > 0) {
                $rows = array_slice($rows, 0, max(0, $cap - self::reviewedOn($student, $today)));
            }
        }
        return $rows;
    }

    /**
     * How many reviews are waiting today, for the menu: a count only, within the daily number. Quizzes whose answers
     * were released since the last visit are added when the student opens the queue.
     */
    public static function dueCount(array $student, DateTimeImmutable $now): int
    {
        $orgId = (int) $student['organization_id'];
        $today = self::today($orgId, $now);
        $due = (int) Db::value('SELECT COUNT(*) FROM review_items r WHERE ' . self::visibleSql($student) . ' AND r.due_on <= :d', ['u' => (int) $student['id'], 'd' => $today]);
        $cap = self::dailyCap($orgId);
        return $cap > 0 ? min($due, max(0, $cap - self::reviewedOn($student, $today))) : $due;
    }

    /** How many items the student reviewed on a local date. */
    public static function reviewedOn(array $student, string $date): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM review_items WHERE user_id = :u AND last_reviewed_on = :d', ['u' => (int) $student['id'], 'd' => $date]);
    }

    /**
     * Checks an answer, moves the item on its schedule and returns what the student sees next. Reviewing ahead of
     * time (before a quiz) does not push an item further out; a miss still brings it back tomorrow.
     * @param array<string,mixed> $input 'choice' (index), 'text' or 'knew' ('1' or '0', flashcards only)
     * @return array{right:bool,answer:string,explanation:?string,source:?string,done:bool,due_on:?string,early:bool}
     */
    public static function answer(array $student, int $itemId, array $input, DateTimeImmutable $now): array
    {
        $row = Db::one('SELECT * FROM review_items WHERE id = :id AND user_id = :u AND done_at IS NULL', ['id' => $itemId, 'u' => (int) $student['id']]);
        if (!$row || !self::releasedOnly($student, Db::all('SELECT r.* FROM review_items r WHERE r.id = :id AND ' . self::visibleSql($student), ['id' => $itemId, 'u' => (int) $student['id']]), $now)) {
            throw HttpException::notFound();
        }
        $item = self::content($row);
        $right = self::check($item, $input);
        $today = self::today((int) $student['organization_id'], $now);
        $early = $row['due_on'] > $today;
        $next = $early && $right
            ? ['step' => (int) $row['step'], 'due_on' => $row['due_on'], 'done' => false]
            : self::next((int) $row['step'], $right, $today);
        Db::update('review_items', [
            'step' => $next['step'],
            'due_on' => $next['due_on'] ?? $row['due_on'],
            'done_at' => $next['done'] ? Db::now() : null,
            'reviews' => (int) $row['reviews'] + 1,
            'lapses' => (int) $row['lapses'] + ($right ? 0 : 1),
            'last_reviewed_on' => $today,
            'updated_at' => Db::now(),
        ], ['id' => $itemId]);
        return [
            'right' => $right,
            'answer' => self::answerText($item),
            'explanation' => $item['explanation'] ?? null,
            'source' => $item['source'] ?? null,
            'done' => $next['done'],
            'due_on' => $next['due_on'],
            'early' => $early,
        ];
    }

    /**
     * Adds the items a student missed in a practice set they just finished (wrong answers, or cards not known yet).
     * An item missed again comes back today, one interval lower.
     * @param list<array<string,mixed>> $items @param array<int,array<string,mixed>> $results
     */
    public static function fromPractice(array $student, array $set, array $items, array $results, DateTimeImmutable $now): int
    {
        if (empty($set['class_id'])) {
            return 0;
        }
        $added = 0;
        $today = self::today((int) $student['organization_id'], $now);
        foreach ($items as $i => $item) {
            $result = $results[$i] ?? [];
            $missed = $set['kind'] === 'quiz' ? empty($result['correct']) : empty($result['known']);
            if (!$missed) {
                continue;
            }
            $content = self::fromPracticeItem($set['kind'], $item);
            if ($content === null) {
                continue;
            }
            $added += self::add($student, (int) $set['class_id'], 'practice', 'practice:' . (int) $set['id'] . ':' . (int) $i, $content,
                ['practice_set_id' => (int) $set['id'], 'material_id' => isset($item['source_ref']['material_id']) ? (int) $item['source_ref']['material_id'] : null], $today);
        }
        return $added;
    }

    /**
     * Adds the questions a student missed (answered wrong or left blank) in their latest attempt at each quiz whose
     * answers they can now see. Questions that need a teacher's marking are left out. Safe to run on every visit.
     */
    public static function sync(array $student, DateTimeImmutable $now): int
    {
        $sid = (int) $student['id'];
        $classIds = Access::studentClassIds($student);
        if (!$classIds) {
            return 0;
        }
        [$in, $params] = Db::in($classIds, 'c');
        $attempts = Db::all(
            "SELECT a.id, a.class_id, a.title, a.due_at, a.answer_key_release, a.answers_released_at, s.id AS submission_id, s.status, s.assignment_version_id
             FROM assignments a
             JOIN submissions s ON s.id = (SELECT s2.id FROM submissions s2 WHERE s2.assignment_id = a.id AND s2.student_id = :u AND s2.status = 'submitted' ORDER BY s2.attempt_no DESC LIMIT 1)
             WHERE a.class_id IN $in AND a.kind = 'quiz' AND a.status IN ('published','closed') AND a.answer_key_release <> 'never'",
            $params + ['u' => $sid]
        );
        $attempts = array_values(array_filter($attempts, static fn (array $a): bool => Assessments::answersReleased($a + ['class_due_at' => $a['due_at']], ['status' => $a['status']])));
        if (!$attempts) {
            return 0;
        }
        [$sin, $sparams] = Db::in(array_map(static fn (array $a): int => (int) $a['submission_id'], $attempts), 's');
        $right = [];
        foreach (Db::all("SELECT submission_id, question_id FROM submission_answers WHERE submission_id IN $sin AND is_correct = 1", $sparams) as $r) {
            $right[(int) $r['submission_id'] . ':' . (int) $r['question_id']] = true;
        }
        // Runs on every visit to the home page: one query for what is already queued, inserts only for what is new.
        $have = array_flip(array_map('strval', Db::column("SELECT source_key FROM review_items WHERE user_id = :u AND source = 'quiz'", ['u' => $sid])));
        $today = self::today((int) $student['organization_id'], $now);
        $added = 0;
        foreach ($attempts as $a) {
            $snapshot = Assessments::snapshotFor($a['assignment_version_id'] !== null ? (int) $a['assignment_version_id'] : null);
            foreach ((array) ($snapshot['questions'] ?? []) as $q) {
                $key = 'quiz:' . (int) $a['id'] . ':' . (int) $q['id'];
                if (isset($have[$key]) || isset($right[(int) $a['submission_id'] . ':' . (int) $q['id']])) {
                    continue;
                }
                $content = self::fromQuestion($q, (string) $a['title']);
                if ($content === null) {
                    continue;
                }
                self::insert($student, (int) $a['class_id'], 'quiz', $key, $content, ['assignment_id' => (int) $a['id']], $today);
                $have[$key] = true;
                $added++;
            }
        }
        return $added;
    }

    /**
     * Quizzes coming up in the student's classes, with how many review items each class has: the way into
     * "review everything before my quiz".
     * @return list<array<string,mixed>>
     */
    public static function upcomingQuizzes(array $student, DateTimeImmutable $now, int $days = 14): array
    {
        $classIds = Access::studentClassIds($student);
        if (!$classIds) {
            return [];
        }
        [$in, $params] = Db::in($classIds, 'c');
        $due = Overrides::dueSql('a');
        $rows = Db::all(
            "SELECT a.id, a.title, a.class_id, $due AS due_at, c.name AS class_name
             FROM assignments a JOIN classes c ON c.id = a.class_id" . Overrides::joinSql('a', 'u1') . "
             WHERE a.class_id IN $in AND a.kind = 'quiz' AND a.status = 'published' AND " . Overrides::notExcusedSql() . "
               AND $due >= :from AND $due <= :to
               AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = :u2 AND s.status = 'submitted')
             ORDER BY $due",
            $params + ['u1' => (int) $student['id'], 'u2' => (int) $student['id'], 'from' => self::utc($now), 'to' => self::utc($now->modify('+' . $days . ' days'))]
        );
        $counts = [];
        foreach ($rows as &$row) {
            $cid = (int) $row['class_id'];
            if (!array_key_exists($cid, $counts)) {
                $counts[$cid] = count(self::queue($student, $now, $cid));
            }
            $row['items'] = $counts[$cid];
        }
        unset($row);
        return $rows;
    }

    /**
     * An item as the review page shows it.
     * @return array<string,mixed> kind 'choice' (prompt, choices, correct), 'text' (prompt, accepted) or 'card' (prompt, back)
     */
    public static function content(array $row): array
    {
        $item = json_decode((string) $row['content'], true) ?: [];
        if (!empty($item['true_false'])) {
            $item['choices'] = [t('True'), t('False')];
        }
        return $item;
    }

    /** @param array<string,mixed> $item from content() @param array<string,mixed> $input */
    public static function check(array $item, array $input): bool
    {
        switch ($item['kind'] ?? '') {
            case 'choice':
                $given = $input['choice'] ?? null;
                return $given !== null && $given !== '' && ctype_digit((string) $given) && (int) $given === (int) ($item['correct'] ?? -1);
            case 'text':
                $norm = static function (string $s, bool $case): string {
                    $s = rtrim(trim((string) preg_replace('/\s+/u', ' ', $s)), '.!');
                    return $case ? $s : mb_strtolower($s);
                };
                $case = !empty($item['case_sensitive']);
                $given = $norm((string) ($input['text'] ?? ''), $case);
                foreach ((array) ($item['accepted'] ?? []) as $accepted) {
                    if ($given !== '' && $given === $norm((string) $accepted, $case)) {
                        return true;
                    }
                }
                return false;
            case 'card':
                return ($input['knew'] ?? '') === '1';
        }
        return false;
    }

    // ---------------------------------------------------------------------------------------------------------

    /** The conditions every review query shares: the student's own items in classes they are still in. */
    private static function visibleSql(array $student): string
    {
        $sql = "r.user_id = :u AND r.done_at IS NULL
            AND EXISTS (SELECT 1 FROM class_memberships cm JOIN classes cc ON cc.id = cm.class_id AND cc.status = 'active'
                        WHERE cm.class_id = r.class_id AND cm.user_id = r.user_id AND cm.role = 'student' AND cm.status = 'active')
            AND (r.material_id IS NULL OR EXISTS (SELECT 1 FROM learning_materials m WHERE m.id = r.material_id AND " . Access::releasedMaterialSql('m') . '))';
        // Practice items were written by AI, so they leave the queue while the student's AI is off.
        if (!AiAccess::allowed($student)) {
            $sql .= " AND r.source <> 'practice'";
        }
        return $sql;
    }

    /**
     * Drops quiz items whose answers the student can no longer see (a teacher changed when they are released).
     * @param list<array<string,mixed>> $rows @return list<array<string,mixed>>
     */
    private static function releasedOnly(array $student, array $rows, DateTimeImmutable $now): array
    {
        $ids = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['assignment_id'], array_filter($rows, static fn (array $r): bool => $r['source'] === 'quiz'))));
        if (!$ids) {
            return $rows;
        }
        [$in, $params] = Db::in($ids, 'a');
        $released = [];
        foreach (Db::all("SELECT id, status, due_at, answer_key_release, answers_released_at FROM assignments WHERE id IN $in", $params) as $a) {
            $released[(int) $a['id']] = in_array($a['status'], ['published', 'closed'], true)
                && Assessments::answersReleased($a + ['class_due_at' => $a['due_at']], ['status' => 'submitted']);
        }
        return array_values(array_filter($rows, static fn (array $r): bool => $r['source'] !== 'quiz' || !empty($released[(int) $r['assignment_id']])));
    }

    /**
     * Adds a practice item, or brings one back today, one step lower, when it is missed again.
     * @param array<string,mixed> $content @param array<string,int|null> $links practice_set_id, material_id
     */
    private static function add(array $student, int $classId, string $source, string $key, array $content, array $links, string $today): int
    {
        $existing = Db::one('SELECT id, step FROM review_items WHERE user_id = :u AND source_key = :k', ['u' => (int) $student['id'], 'k' => $key]);
        if ($existing) {
            Db::update('review_items', ['step' => max(0, (int) $existing['step'] - 1), 'due_on' => $today, 'done_at' => null, 'updated_at' => Db::now()], ['id' => (int) $existing['id']]);
            return 0;
        }
        self::insert($student, $classId, $source, $key, $content, $links, $today);
        return 1;
    }

    /** @param array<string,mixed> $content @param array<string,int|null> $links practice_set_id, assignment_id, material_id */
    private static function insert(array $student, int $classId, string $source, string $key, array $content, array $links, string $today): void
    {
        Db::insert('review_items', [
            'organization_id' => (int) $student['organization_id'],
            'user_id' => (int) $student['id'],
            'class_id' => $classId,
            'source' => $source,
            'source_key' => $key,
            'practice_set_id' => $links['practice_set_id'] ?? null,
            'assignment_id' => $links['assignment_id'] ?? null,
            'material_id' => $links['material_id'] ?? null,
            'kind' => $content['kind'],
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
            'step' => 0,
            'due_on' => $today,
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
    }

    /** @return array<string,mixed>|null */
    private static function fromPracticeItem(string $kind, array $item): ?array
    {
        $source = isset($item['source_ref']['title']) ? trim((string) $item['source_ref']['title'] . (!empty($item['source_ref']['where']) ? ', ' . $item['source_ref']['where'] : '')) : null;
        if ($kind === 'flashcards') {
            return isset($item['front'], $item['back']) ? ['kind' => 'card', 'prompt' => (string) $item['front'], 'back' => (string) $item['back'], 'source' => $source] : null;
        }
        if (($item['type'] ?? 'mcq') === 'mcq') {
            return isset($item['question'], $item['choices'], $item['answer']) && $item['answer'] !== false
                ? ['kind' => 'choice', 'prompt' => (string) $item['question'], 'choices' => array_values(array_map('strval', (array) $item['choices'])), 'correct' => (int) $item['answer'],
                    'explanation' => $item['explanation'] ?? null, 'source' => $source]
                : null;
        }
        return isset($item['question'], $item['answer_text'])
            ? ['kind' => 'text', 'prompt' => (string) $item['question'], 'accepted' => [(string) $item['answer_text']], 'case_sensitive' => false,
                'explanation' => $item['explanation'] ?? null, 'source' => $source]
            : null;
    }

    /** A quiz question from the attempt's snapshot; essays need a teacher, so they are left out. @return array<string,mixed>|null */
    private static function fromQuestion(array $q, string $title): ?array
    {
        $options = is_array($q['options']) ? $q['options'] : (json_decode((string) $q['options'], true) ?: []);
        $key = is_array($q['answer_key']) ? $q['answer_key'] : (json_decode((string) $q['answer_key'], true) ?: []);
        $base = ['prompt' => (string) $q['prompt'], 'explanation' => $q['explanation'] ?? null, 'source' => $title];
        switch ($q['qtype']) {
            case 'multiple_choice':
                $correct = array_search((string) ($key['correct'] ?? ''), array_map(static fn ($o) => (string) $o['key'], $options), true);
                return $correct === false ? null : $base + ['kind' => 'choice', 'choices' => array_map(static fn ($o) => (string) $o['text'], $options), 'correct' => (int) $correct];
            case 'true_false':
                return $base + ['kind' => 'choice', 'true_false' => true, 'correct' => !empty($key['correct']) ? 0 : 1];
            case 'short_answer':
                $accepted = array_values(array_filter(array_map('strval', (array) ($key['accepted'] ?? []))));
                return $accepted ? $base + ['kind' => 'text', 'accepted' => $accepted, 'case_sensitive' => !empty($key['case_sensitive'])] : null;
        }
        return null;
    }

    /** The right answer in words, shown after each answer. */
    private static function answerText(array $item): string
    {
        switch ($item['kind'] ?? '') {
            case 'choice':
                return (string) ($item['choices'][(int) ($item['correct'] ?? 0)] ?? '');
            case 'text':
                return (string) ($item['accepted'][0] ?? '');
            case 'card':
                return (string) ($item['back'] ?? '');
        }
        return '';
    }

    private static function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function addDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date))->modify('+' . $days . ' days')->format('Y-m-d');
    }
}
