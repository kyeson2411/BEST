<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;

/** Plain-language descriptions of audit actions, plus bulk lookup of what each entry refers to. */
final class AuditLabels
{
    /** Filter groups for the audit log, keyed by action prefix. */
    public static function groups(): array
    {
        return [
            'auth' => t('Sign-in and passwords'),
            'user' => t('People'),
            'class' => t('Classes'),
            'material' => t('Materials'),
            'lesson' => t('Lessons'),
            'assignment' => t('Assignments'),
            'submission' => t('Submissions and grading'),
            'settings' => t('Settings and policies'),
            'grading_period' => t('Grading periods'),
            'privacy' => t('Privacy'),
            'notices' => t('Privacy notice and rules'),
            'feedback' => t('Feedback about Sibol'),
            'pilot' => t('Pilot report'),
            'ai' => t('Companion and AI'),
            'access' => t('Denied access'),
        ];
    }

    public static function describe(array $row): string
    {
        $meta = json_decode((string) ($row['metadata'] ?? ''), true) ?: [];
        $n = static fn (string $key): int => (int) ($meta[$key] ?? 0);
        switch ((string) $row['action']) {
            case 'auth.login': return t('Signed in');
            case 'auth.demo_login': return t('Signed in with a demo account');
            case 'auth.logout': return t('Signed out');
            case 'auth.invitation_accepted': return t('Accepted an invitation and set a password');
            case 'auth.password_reset': return t('Set a new password with a reset link');
            case 'auth.password_changed': return t('Changed their password');
            case 'auth.sessions_revoked': return t('Signed out their other sessions');
            case 'access.denied': return t('Was refused access to a page');

            case 'user.invited': return t('Invited a :role', ['role' => lcfirst_u(role_label((string) ($meta['role'] ?? 'student')))]);
            case 'user.updated': return t('Updated account details');
            case 'user.role_changed': return t('Changed a role to :role', ['role' => lcfirst_u(role_label((string) ($meta['role'] ?? '')))]);
            case 'user.deactivated': return t('Deactivated an account');
            case 'user.reactivated': return t('Reactivated an account');
            case 'user.invitation_link': return t('Created a new invitation link');
            case 'user.reset_link': return t('Created a password reset link');
            case 'users.imported': return tp('Imported :count person from CSV', 'Imported :count people from CSV', $n('created'));
            case 'user.ai_switched':
                return ['on' => t('Switched the AI companion on for a student'), 'off' => t('Switched the AI companion off for a student')][$meta['setting'] ?? ''] ?? t('Set a student\'s AI companion to follow the school');
            case 'user.guardian_answer': return ($meta['answer'] ?? '') === 'yes' ? t('Recorded a guardian\'s yes to the AI companion') : t('Recorded a guardian\'s no to the AI companion');
            case 'notices.acknowledged': return t('Accepted the privacy notice and the rules');
            case 'notices.renewed': return t('Asked everyone to accept the privacy notice and the rules again');
            case 'user.setup_slip': return t('Printed a sign-in slip');
            case 'user.reset_slip': return t('Printed a password slip');
            case 'user.signin_slips':
                return tp('Printed :count sign-in or password slip', 'Printed :count sign-in or password slips', $n('setup') + $n('reset'));
            case 'section.adviser_changed': return !empty($meta['has_adviser']) ? t('Chose a section adviser') : t('Removed a section adviser');
            case 'section.removed': return t('Removed an empty section');

            case 'class.created': return t('Created a class');
            case 'class.updated': return t('Updated class details');
            case 'class.archived': return t('Archived a class');
            case 'class.restored': return t('Restored a class');
            case 'class.member_added': return tp('Added :count person to a class', 'Added :count people to a class', max(1, $n('count')));
            case 'class.member_removed': return t('Removed someone from a class');
            case 'class.student_added': return t('Added a student to a class');
            case 'class.student_withdrawn': return t('Withdrew a student from a class');
            case 'class.joined_by_code': return t('Joined a class with a class code');
            case 'class.code_created': return t('Created a class code');
            case 'class.code_disabled': return t('Turned off a class code');
            case 'announcement.posted': return t('Posted an announcement');
            case 'subject.created': return t('Added a subject');
            case 'subject.updated': return t('Updated a subject');
            case 'period.created': return t('Added an academic period');
            case 'period.updated': return t('Updated an academic period');

            case 'material.created': return t('Added a material');
            case 'material.updated': return t('Updated a material');
            case 'material.published': return t('Published a material');
            case 'material.unpublished': return t('Unpublished a material');
            case 'material.archived': return t('Archived a material');
            case 'material.version_uploaded': return t('Uploaded a new version of a material');
            case 'material.copied': return t('Copied a material to another section');
            case 'lesson.created': return t('Created a lesson');
            case 'lesson.copied': return t('Copied a lesson to another section');
            case 'lesson.published': return t('Published a lesson');
            case 'lesson.unpublished': return t('Unpublished a lesson');

            case 'assignment.created': return t('Created an assignment');
            case 'assignment.updated': return t('Edited an assignment');
            case 'assignment.published': return t('Published an assignment');
            case 'assignment.closed': return t('Closed an assignment');
            case 'assignment.reopened': return t('Reopened an assignment');
            case 'assignment.archived': return t('Archived an assignment');
            case 'assignment.duplicated': return t('Duplicated an assignment');
            case 'assignment.copied': return t('Copied an assignment to another section');
            case 'assignment.override_saved':
                return !empty($meta['excused']) ? t('Excused a student from an assignment') : t('Gave a student their own due date or extra time');
            case 'assignment.override_removed': return t('Put a student back on the class due date');
            case 'assignment.paper_scores_saved':
                return $n('released') > 0
                    ? tp('Saved scores for work on paper and released :count', 'Saved scores for work on paper and released :count', $n('released'))
                    : t('Saved scores for work on paper');
            case 'assignment.printed': return ($meta['copy'] ?? '') === 'key' ? t('Opened the answer key to print') : t('Opened a blank copy to print');
            case 'assignment.deleted': return t('Deleted a draft assignment');
            case 'assignment.answers_released': return t('Released correct answers');
            case 'assignment.ai_questions_drafted': return tp('Drafted :count question with AI', 'Drafted :count questions with AI', max(1, $n('count')));
            case 'assignment.ai_questions_approved': return t('Approved AI-drafted questions');

            case 'submission.submitted': return t('Handed in work');
            case 'quiz.started': return t('Started a quiz');
            case 'quiz.submitted': return t('Submitted a quiz');
            case 'submission.graded': return t('Graded a submission');
            case 'submission.feedback_released':
                return $n('count') > 1 ? tp('Released feedback to :count student', 'Released feedback to :count students', $n('count')) : t('Released feedback');
            case 'submission.returned': return t('Returned work for resubmission');
            case 'submission.reopened': return t('Reopened a quiz attempt');
            case 'submission.ai_suggestion': return t('Asked for an AI grading suggestion');
            case 'gradebook.exported': return t('Exported a gradebook');
            case 'insights.ideas_requested': return t('Asked for reteaching ideas');

            case 'settings.policies_updated': return t('Changed school policies');
            case 'settings.school_updated': return t('Changed school details');
            case 'settings.grading_updated': return t('Changed the grading setup');
            case 'settings.grading_groups_updated': return t('Changed the weights for subject groups');
            case 'settings.transmutation_updated':
                return !empty($meta['enabled']) ? t('Switched the transmutation table on') : (!empty($meta['confirmed']) ? t('Saved a checked transmutation table, switched off') : t('Changed the transmutation table'));
            case 'grading_period.created': return t('Added a grading period');
            case 'grading_period.updated': return t('Changed the dates of a grading period');
            case 'grading_period.deleted': return t('Removed a grading period');
            case 'grading_period.split': return tp('Split a school year into :count grading period', 'Split a school year into :count grading periods', $n('count'));
            case 'settings.logo_updated': return t('Changed the school logo');

            case 'privacy.request_submitted': return t('Submitted a privacy request');
            case 'privacy.request_updated': return t('Updated a privacy request');
            case 'privacy.export_created': return t('Prepared a data export');
            case 'privacy.export_downloaded': return t('Downloaded their data export');
            case 'privacy.erased': return t('Erased a person\'s data');
            case 'privacy.retention_purge':
                return tp('Removed :count conversation past the retention period', 'Removed :count conversations past the retention period', $n('conversations'));

            case 'feedback.sent': return t('Sent feedback about Sibol');
            case 'feedback.updated': return !empty($meta['replied']) ? t('Replied to feedback about Sibol') : t('Changed the status of feedback about Sibol');
            case 'pilot.report_exported': return t('Downloaded the weekly pilot report');

            case 'ai.conversation_deleted': return t('Deleted a companion conversation');
            case 'ai.answer_reported': return t('Reported a companion answer');
            case 'ai.excerpt_shared': return t('Shared a companion excerpt with a teacher');
            case 'ai.report_viewed': return t('Opened a reported answer');
            case 'ai.report_resolved': return t('Closed a report about a companion answer');
            case 'ai.eval_run': return t('Ran the AI check: :passed of :cases cases passed', ['passed' => $n('passed'), 'cases' => $n('cases')]);
        }
        return ucfirst(str_replace(['.', '_'], ' ', (string) $row['action']));
    }

    /**
     * Resolves the target of each row to a name and an admin link, with one query per target type.
     * Targets outside the administrator's school are never resolved.
     * @param list<array> $rows
     * @return array<string,array{label:string,url:?string}> keyed by "type:id"
     */
    public static function targets(array $rows, int $orgId): array
    {
        $ids = [];
        foreach ($rows as $r) {
            if ($r['target_type'] && $r['target_id']) {
                $ids[$r['target_type']][] = (int) $r['target_id'];
            }
        }
        $queries = [
            'user' => ["SELECT id, CONCAT(COALESCE(NULLIF(preferred_name, ''), given_name), ' ', family_name) AS label FROM users WHERE organization_id = :o AND id IN %s", '/admin/users/'],
            'class' => ['SELECT id, name AS label FROM classes WHERE organization_id = :o AND id IN %s', '/admin/classes/'],
            'material' => ['SELECT id, title AS label FROM learning_materials WHERE organization_id = :o AND id IN %s', null],
            'assignment' => ['SELECT id, title AS label FROM assignments WHERE organization_id = :o AND id IN %s', null],
            'lesson' => ['SELECT l.id, l.title AS label FROM lessons l JOIN classes c ON c.id = l.class_id WHERE c.organization_id = :o AND l.id IN %s', null],
            'subject' => ['SELECT id, name AS label FROM subjects WHERE organization_id = :o AND id IN %s', null],
            'period' => ['SELECT id, name AS label FROM academic_periods WHERE organization_id = :o AND id IN %s', null],
            'section' => ["SELECT id, CONCAT(COALESCE(CONCAT(grade_level, ' – '), ''), name) AS label FROM sections WHERE organization_id = :o AND id IN %s", '/admin/sections#section-'],
            'privacy_request' => ["SELECT id, CONCAT('#', id) AS label FROM privacy_requests WHERE organization_id = :o AND id IN %s", '/admin/privacy/requests/'],
            'ai_report' => ["SELECT id, CONCAT('#', id) AS label FROM ai_reports WHERE organization_id = :o AND id IN %s", '/admin/privacy/reports/'],
            'feedback' => ["SELECT id, CONCAT('#', id) AS label FROM site_feedback WHERE organization_id = :o AND id IN %s", '/admin/feedback/'],
        ];
        $out = [];
        foreach ($ids as $type => $list) {
            if (!isset($queries[$type])) {
                continue;
            }
            [$sql, $link] = $queries[$type];
            [$in, $params] = Db::in(array_values(array_unique($list)), 't');
            foreach (Db::all(sprintf($sql, $in), $params + ['o' => $orgId]) as $t) {
                $out[$type . ':' . $t['id']] = ['label' => (string) $t['label'], 'url' => $link ? $link . $t['id'] : null];
            }
        }
        return $out;
    }
}
