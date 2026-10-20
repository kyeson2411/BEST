<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\Uploads;

/**
 * Data-subject requests (export, erasure) and retention. Every action is explicit,
 * reviewed by an administrator, and recorded in the audit log without personal content.
 */
final class Privacy
{
    public const EXPORT_DAYS = 14;

    /** Builds a JSON export of everything Sibol holds about a person and stores it outside the web root. */
    public static function export(int $userId): string
    {
        $user = Db::one('SELECT id, role, username, email, given_name, family_name, preferred_name, grade_level, language, timezone, status, created_at, last_login_at FROM users WHERE id = :u', ['u' => $userId]);
        $aiSetting = Db::value('SELECT ai_enabled FROM users WHERE id = :u', ['u' => $userId]);
        $data = [
            'generated_at' => gmdate('c'),
            'about' => 'This file contains the information Sibol holds about you. Times are in UTC.',
            'account' => $user,
            'classes' => Db::all('SELECT c.name AS class, cm.role, cm.status, cm.joined_at, cm.withdrawn_at FROM class_memberships cm JOIN classes c ON c.id = cm.class_id WHERE cm.user_id = :u', ['u' => $userId]),
            'submissions' => array_map(static function (array $s): array {
                $s['answers'] = Db::all('SELECT q.prompt AS question, sa.response, sa.points_awarded, sa.teacher_comment FROM submission_answers sa JOIN questions q ON q.id = sa.question_id WHERE sa.submission_id = :s', ['s' => (int) $s['id']]);
                $s['files'] = Db::column('SELECT original_filename FROM submission_files WHERE submission_id = :s', ['s' => (int) $s['id']]);
                if ($s['feedback_status'] !== 'released') {
                    unset($s['score'], $s['feedback']);
                }
                unset($s['id']);
                return $s;
            }, Db::all('SELECT s.id, a.title AS assignment, s.attempt_no, s.status, s.body, s.submitted_at, s.is_late, s.score, s.points_possible, s.feedback, s.feedback_status, s.receipt_code FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.student_id = :u', ['u' => $userId])),
            'due_date_changes' => Db::all('SELECT a.title AS assignment, o.due_at, o.extra_minutes, o.excused, o.updated_at FROM assignment_overrides o JOIN assignments a ON a.id = o.assignment_id WHERE o.student_id = :u', ['u' => $userId]),
            'companion_conversations' => array_map(static function (array $c): array {
                $c['messages'] = Db::all('SELECT role, content, created_at FROM ai_messages WHERE conversation_id = :c ORDER BY id', ['c' => (int) $c['id']]);
                unset($c['id']);
                return $c;
            }, Db::all('SELECT id, title, mode, created_at, last_message_at FROM ai_conversations WHERE user_id = :u', ['u' => $userId])),
            'practice' => Db::all('SELECT title, kind, score, total, created_at, completed_at FROM practice_sets WHERE user_id = :u', ['u' => $userId]),
            'review_queue' => array_map(static function (array $r): array {
                $r['item'] = json_decode((string) $r['item'], true);
                return $r;
            }, Db::all('SELECT source, content AS item, step, reviews, lapses, due_on, last_reviewed_on, done_at, created_at FROM review_items WHERE user_id = :u ORDER BY id', ['u' => $userId])),
            'study_sessions' => Db::all('SELECT title, notes, starts_at, duration_minutes, completed_at FROM study_sessions WHERE user_id = :u', ['u' => $userId]),
            'shared_with_teachers' => Db::all('SELECT kind, content, student_note, created_at FROM shared_excerpts WHERE student_id = :u', ['u' => $userId]),
            'notifications' => Db::all('SELECT title, body, created_at, read_at FROM notifications WHERE user_id = :u ORDER BY created_at DESC LIMIT 500', ['u' => $userId]),
            'privacy_requests' => Db::all('SELECT request_type, status, created_at, resolved_at FROM privacy_requests WHERE user_id = :u', ['u' => $userId]),
            'feedback_sent' => Db::all('SELECT category, body, page_path, status, reply, replied_at, created_at FROM site_feedback WHERE user_id = :u ORDER BY created_at', ['u' => $userId]),
            'comment_bank' => Db::all('SELECT body, criterion, uses, created_at FROM comment_bank WHERE user_id = :u ORDER BY id', ['u' => $userId]),
            'notices_accepted' => Db::all('SELECT notice, version, acknowledged_at FROM notice_acknowledgments WHERE user_id = :u ORDER BY acknowledged_at', ['u' => $userId]),
            'ai_companion' => [
                'setting' => $aiSetting === null ? 'school default' : ((int) $aiSetting === 1 ? 'on' : 'off'),
                'guardian_answers' => Db::all("SELECT answer, answered_on, created_at AS recorded_at FROM guardian_consents WHERE student_id = :u AND topic = 'ai' ORDER BY answered_on", ['u' => $userId]),
            ],
        ];
        $stored = Uploads::storeContent((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'exports', 'export.json');
        return $stored['path'];
    }

    /**
     * Erases personal data. Private study data is deleted; the account is anonymised.
     * School academic records (submissions and grades) are deleted only when $includeRecords is true.
     * @return array<string,int> counts of removed items
     */
    public static function erase(int $userId, bool $includeRecords): array
    {
        $user = Db::one('SELECT * FROM users WHERE id = :u', ['u' => $userId]);
        if (!$user) {
            return [];
        }
        $counts = [];
        Db::transaction(static function () use ($user, $userId, $includeRecords, &$counts): void {
            $counts['conversations'] = Db::exec('DELETE FROM ai_conversations WHERE user_id = :u', ['u' => $userId]);
            $counts['review_items'] = Db::exec('DELETE FROM review_items WHERE user_id = :u', ['u' => $userId]);
            $counts['practice_sets'] = Db::exec('DELETE FROM practice_sets WHERE user_id = :u', ['u' => $userId]);
            $counts['study_sessions'] = Db::exec('DELETE FROM study_sessions WHERE user_id = :u', ['u' => $userId]);
            $counts['shared_excerpts'] = Db::exec('DELETE FROM shared_excerpts WHERE student_id = :u', ['u' => $userId]);
            $counts['notifications'] = Db::exec('DELETE FROM notifications WHERE user_id = :u', ['u' => $userId]);
            Db::exec('DELETE FROM content_views WHERE user_id = :u', ['u' => $userId]);
            Db::exec('DELETE FROM notice_acknowledgments WHERE user_id = :u', ['u' => $userId]);
            Db::exec('DELETE FROM recovery_codes WHERE user_id = :u', ['u' => $userId]);
            $counts['feedback'] = Db::exec('DELETE FROM site_feedback WHERE user_id = :u', ['u' => $userId]);
            $counts['comment_bank'] = Db::exec('DELETE FROM comment_bank WHERE user_id = :u', ['u' => $userId]);
            Db::exec('UPDATE user_tokens SET revoked_at = :n WHERE user_id = :u AND used_at IS NULL AND revoked_at IS NULL', ['n' => Db::now(), 'u' => $userId]);
            if ($includeRecords) {
                foreach (Db::column('SELECT f.stored_path FROM submission_files f JOIN submissions s ON s.id = f.submission_id WHERE s.student_id = :u', ['u' => $userId]) as $path) {
                    Uploads::delete((string) $path);
                }
                $counts['submissions'] = Db::exec('DELETE FROM submissions WHERE student_id = :u', ['u' => $userId]);
                Db::exec('DELETE FROM assignment_overrides WHERE student_id = :u', ['u' => $userId]);
                Db::exec('DELETE FROM guardian_consents WHERE student_id = :u', ['u' => $userId]);
            }
            Uploads::delete($user['avatar_path'] ?? null);
            Db::update('users', [
                'given_name' => 'Former',
                'family_name' => ucfirst((string) $user['role']) . ' ' . $userId,
                'preferred_name' => null,
                'email' => null,
                'username' => null,
                'password_hash' => null,
                'totp_secret' => null,
                'totp_enabled_at' => null,
                'totp_last_step' => null,
                'avatar_path' => null,
                'preferences' => null,
                'language' => null,
                'timezone' => null,
                'grade_level' => null,
                'status' => 'deactivated',
                'deactivated_at' => $user['deactivated_at'] ?? Db::now(),
                'anonymized_at' => Db::now(),
                'session_version' => (int) $user['session_version'] + 1,
                'updated_at' => Db::now(),
            ], ['id' => $userId]);
            Db::exec("UPDATE class_memberships SET status = 'withdrawn', withdrawn_at = :n WHERE user_id = :u AND status = 'active'", ['n' => Db::now(), 'u' => $userId]);
        });
        return $counts;
    }

    /** Deletes companion conversations older than each school's retention period. */
    public static function purgeExpiredConversations(): string
    {
        $total = 0;
        foreach (Db::all('SELECT id FROM organizations') as $org) {
            $days = (int) Settings::get((int) $org['id'], 'privacy.ai_retention_days', 365);
            if ($days <= 0) {
                continue;
            }
            $deleted = Db::exec(
                'DELETE FROM ai_conversations WHERE organization_id = :o AND last_message_at < :cut',
                ['o' => (int) $org['id'], 'cut' => gmdate('Y-m-d H:i:s', time() - $days * 86400)]
            );
            if ($deleted > 0) {
                Audit::log('privacy.retention_purge', 'organization', (int) $org['id'], ['conversations' => $deleted, 'organization_id' => (int) $org['id']]);
            }
            $total += $deleted;
            // Expired export files are removed too.
            foreach (Db::all("SELECT id, export_path FROM privacy_requests WHERE organization_id = :o AND export_path IS NOT NULL AND export_expires_at < UTC_TIMESTAMP()", ['o' => (int) $org['id']]) as $r) {
                Uploads::delete((string) $r['export_path']);
                Db::update('privacy_requests', ['export_path' => null], ['id' => (int) $r['id']]);
            }
        }
        return sprintf('Removed %d conversation(s) past their retention period.', $total);
    }

    /** Runs the purge at most once a day, lazily (no cron needed under XAMPP). */
    public static function purgeDaily(int $orgId): void
    {
        $last = (string) Settings::get($orgId, 'maintenance.last_purge', '');
        if ($last !== '' && $last >= gmdate('Y-m-d')) {
            return;
        }
        self::purgeExpiredConversations();
        Settings::set($orgId, 'maintenance', ['last_purge' => gmdate('Y-m-d')]);
    }
}
