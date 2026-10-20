<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Db;

/**
 * Whether a person may use the AI companion and AI-made practice. The school's switch applies to everyone;
 * a student can also have AI switched off (or on) on their own by an adviser or administrator, for example
 * when a guardian says no. When it is off, the companion and AI practice disappear for that student and
 * nothing about them is sent to an AI provider; the rest of their work is unaffected.
 * Guardians' answers are kept as dated yes or no records with who entered them.
 */
final class AiAccess
{
    public static function allowed(array $user): bool
    {
        $orgId = (int) $user['organization_id'];
        if (!Settings::get($orgId, 'ai.enabled', true)) {
            return false;
        }
        return $user['role'] !== 'student' || self::studentOn($user);
    }

    /** The student's own setting: their explicit choice, or the school's starting value. */
    public static function studentOn(array $student): bool
    {
        $own = $student['ai_enabled'] ?? null;
        if ($own !== null && $own !== '') {
            return (int) $own === 1;
        }
        return (bool) Settings::get((int) $student['organization_id'], 'ai.students_start_on', true);
    }

    /** SQL condition (users alias $alias) for students whose AI is on at this school. */
    public static function studentSql(string $alias, int $orgId): string
    {
        return Settings::get($orgId, 'ai.students_start_on', true)
            ? "COALESCE($alias.ai_enabled, 1) = 1"
            : "$alias.ai_enabled = 1";
    }

    /** @return bool|null true or false when set for this student, null when following the school */
    public static function explicit(array $student): ?bool
    {
        $own = $student['ai_enabled'] ?? null;
        return $own === null || $own === '' ? null : (int) $own === 1;
    }

    /** Switches a student's AI on or off, or back to the school's setting (null). */
    public static function set(array $actor, array $student, ?bool $on): void
    {
        if (self::explicit($student) === $on) {
            return;
        }
        Db::update('users', ['ai_enabled' => $on === null ? null : ($on ? 1 : 0), 'updated_at' => Db::now()], ['id' => (int) $student['id']]);
        Audit::log('user.ai_switched', 'user', (int) $student['id'], ['setting' => $on === null ? 'school' : ($on ? 'on' : 'off')], $actor);
    }

    /**
     * Records a guardian's answer about AI. A "no" switches the student's AI off; a "yes" switches it on.
     * Nothing but the answer, its date and who entered it is stored.
     */
    public static function recordGuardian(array $actor, array $student, bool $yes, string $answeredOn): void
    {
        Db::insert('guardian_consents', [
            'organization_id' => (int) $student['organization_id'],
            'student_id' => (int) $student['id'],
            'topic' => 'ai',
            'answer' => $yes ? 'yes' : 'no',
            'answered_on' => $answeredOn,
            'recorded_by' => (int) $actor['id'],
            'created_at' => Db::now(),
        ]);
        Audit::log('user.guardian_answer', 'user', (int) $student['id'], ['topic' => 'ai', 'answer' => $yes ? 'yes' : 'no'], $actor);
        self::set($actor, $student, $yes);
    }

    /** @return list<array<string,mixed>> newest first, with who entered each answer */
    public static function guardianAnswers(int $studentId): array
    {
        return Db::all(
            "SELECT g.answer, g.answered_on, g.created_at, u.given_name, u.family_name, u.preferred_name, u.role
             FROM guardian_consents g LEFT JOIN users u ON u.id = g.recorded_by
             WHERE g.student_id = :s AND g.topic = 'ai' ORDER BY g.answered_on DESC, g.id DESC",
            ['s' => $studentId]
        );
    }
}
