<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Core\ImageSanitizer;
use Sibol\Core\Response;
use Sibol\Core\Uploads;
use Sibol\Services\Ai\Providers;
use Sibol\Services\Notices;
use Sibol\Services\Settings;
use Sibol\Services\SystemCheck;

/** School policies (AI, features, class codes, privacy, notifications) and school details. Grading has its own page. */
final class SettingsController extends Controller
{
    public function policies(): Response
    {
        $o = $this->orgId();
        return $this->view('admin/policies', [
            'title' => t('Policies'),
            'settings' => Settings::all($o),
            'provider' => Providers::label(),
            'noticeProgress' => Notices::progress($o),
            'noticeVersions' => Notices::current($o),
        ]);
    }

    public function savePolicies(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $back = '/admin/policies';
        $data = $this->validate([
            'ai_enabled' => 'bool',
            'teacher_tools' => 'bool',
            'general_answers' => 'bool',
            'student_modes' => 'nullable|array',
            'student_daily_messages' => 'required|int|min:0|max:1000',
            'teacher_daily_requests' => 'required|int|min:0|max:2000',
            'monthly_token_budget' => 'required|int|min:0|max:1000000000',
            'max_question_chars' => 'required|int|min:200|max:8000',
            'document_answers' => 'bool',
            'flashcards' => 'bool',
            'practice_quizzes' => 'bool',
            'study_plans' => 'bool',
            'share_with_teacher' => 'bool',
            'spaced_review' => 'bool',
            'review_daily_cap' => 'required|int|min:0|max:200',
            'codes_enabled' => 'bool',
            'code_ttl_days' => 'required|int|min:1|max:180',
            'ai_retention_days' => 'required|int|min:0|max:3650',
            'students_can_delete_chats' => 'bool',
            'contact_name' => 'nullable|string|max:120',
            'contact_email' => 'nullable|email',
            'help_text' => 'nullable|string|max:1000',
            'deadline_reminder_hours' => 'required|int|min:0|max:168',
            'notify_new_material' => 'bool',
            'notify_new_assignment' => 'bool',
            'notify_feedback_released' => 'bool',
            'notify_announcements' => 'bool',
            'students_start_on' => 'bool',
            'notices_students' => 'bool',
            'notices_staff' => 'bool',
        ], [
            'student_daily_messages' => t('Messages per student per day'),
            'teacher_daily_requests' => t('Requests per teacher per day'),
            'monthly_token_budget' => t('Monthly token budget'),
            'max_question_chars' => t('Longest question'),
            'code_ttl_days' => t('Class code lifetime'),
            'review_daily_cap' => t('Reviews per student per day'),
            'ai_retention_days' => t('Conversation retention'),
            'contact_name' => t('Privacy contact'),
            'contact_email' => t('Privacy contact email'),
            'help_text' => t('Help message'),
            'deadline_reminder_hours' => t('Deadline reminder'),
        ], $back);
        $modes = array_values(array_intersect(Settings::STUDENT_MODES, array_map('strval', (array) ($data['student_modes'] ?? []))));
        if ($data['ai_enabled'] && !$modes) {
            $this->fail('student_modes', t('Keep at least one companion mode for students, or turn AI tools off.'), $back);
        }
        $before = Settings::all($o);
        $new = [
            'ai' => [
                'enabled' => $data['ai_enabled'],
                'teacher_tools' => $data['teacher_tools'],
                'general_answers' => $data['general_answers'],
                'student_modes' => $modes,
                'student_daily_messages' => $data['student_daily_messages'],
                'teacher_daily_requests' => $data['teacher_daily_requests'],
                'monthly_token_budget' => $data['monthly_token_budget'],
                'max_question_chars' => $data['max_question_chars'],
                'students_start_on' => $data['students_start_on'],
            ],
            'notices' => [
                'students' => $data['notices_students'],
                'staff' => $data['notices_staff'],
                'edition' => max(1, (int) Settings::get($o, 'notices.edition', 1)),
            ],
            'features' => [
                'document_answers' => $data['document_answers'],
                'flashcards' => $data['flashcards'],
                'practice_quizzes' => $data['practice_quizzes'],
                'study_plans' => $data['study_plans'],
                'share_with_teacher' => $data['share_with_teacher'],
                'spaced_review' => $data['spaced_review'],
            ],
            'review' => ['daily_cap' => $data['review_daily_cap']],
            'class_codes' => ['enabled' => $data['codes_enabled'], 'ttl_days' => $data['code_ttl_days']],
            'privacy' => [
                'ai_retention_days' => $data['ai_retention_days'],
                'students_can_delete_chats' => $data['students_can_delete_chats'],
                'contact_name' => (string) $data['contact_name'],
                'contact_email' => (string) $data['contact_email'],
                'help_text' => (string) $data['help_text'],
            ],
            'notifications' => [
                'deadline_reminder_hours' => $data['deadline_reminder_hours'],
                'new_material' => $data['notify_new_material'],
                'new_assignment' => $data['notify_new_assignment'],
                'feedback_released' => $data['notify_feedback_released'],
                'announcements' => $data['notify_announcements'],
            ],
        ];
        $changed = [];
        foreach ($new as $key => $value) {
            if (array_intersect_key((array) $before[$key], $value) != $value) {
                $changed[] = $key;
                Settings::set($o, $key, array_replace((array) $before[$key], $value), (int) $admin['id']);
            }
        }
        if (!$data['codes_enabled'] && ($before['class_codes']['enabled'] ?? true)) {
            Db::exec('UPDATE classes SET join_code_enabled = 0 WHERE organization_id = :o', ['o' => $o]);
        }
        if ($changed) {
            Audit::log('settings.policies_updated', null, null, ['sections' => implode(',', $changed)]);
        }
        $this->flash('success', $changed ? t('Policies saved. They apply from the next request.') : t('No changes to save.'));
        return $this->redirect($back);
    }

    /** After the school changes how it uses Sibol: everyone the policy names reads and accepts both notices again. */
    public function renewNotices(): Response
    {
        $admin = $this->user();
        $edition = Notices::askAgain($admin);
        $this->flash('success', t('Done. People will read and accept the privacy notice and the rules again when they next open Sibol (edition :n).', ['n' => $edition]));
        return $this->redirect('/admin/policies#notices');
    }

    public function school(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        return $this->view('admin/school', [
            'title' => t('School settings'),
            'org' => Db::one('SELECT * FROM organizations WHERE id = :o', ['o' => $o]),
            'grades' => (array) Settings::get($o, 'grade_levels', []),
        ]);
    }

    public function saveSchool(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $back = '/admin/settings';
        $data = $this->validate([
            'name' => 'required|string|max:160',
            'short_name' => 'nullable|string|max:60',
            'timezone' => 'required|timezone',
            'default_language' => 'required|in:en,fil',
            'grade_levels' => 'nullable|string|max:2000',
        ], ['name' => t('School name'), 'short_name' => t('Short name'), 'timezone' => t('Timezone'), 'default_language' => t('Default language'), 'grade_levels' => t('Grade levels')], $back);
        $grades = array_values(array_unique(array_filter(array_map(static fn ($g) => mb_substr(trim($g), 0, 40), explode("\n", (string) $data['grade_levels'])), static fn ($g) => $g !== '')));
        if (count($grades) > 30) {
            $this->fail('grade_levels', t('Use 30 grade levels or fewer.'), $back);
        }
        Db::update('organizations', [
            'name' => $data['name'],
            'short_name' => $data['short_name'],
            'timezone' => $data['timezone'],
            'default_language' => $data['default_language'],
            'updated_at' => Db::now(),
        ], ['id' => $o]);
        Settings::set($o, 'grade_levels', $grades, (int) $admin['id']);
        Audit::log('settings.school_updated');
        $this->flash('success', t('School details saved.'));
        return $this->redirect($back);
    }

    public function saveLogo(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $back = '/admin/settings#logo';
        $current = (string) Db::value('SELECT logo_path FROM organizations WHERE id = :o', ['o' => $o]);
        if ($this->request->bool('remove')) {
            Uploads::delete($current ?: null);
            Db::update('organizations', ['logo_path' => null, 'updated_at' => Db::now()], ['id' => $o]);
            Audit::log('settings.logo_updated', null, null, ['removed' => true]);
            $this->flash('success', t('Logo removed.'));
            return $this->redirect($back);
        }
        $file = $this->request->file('logo');
        $problem = Uploads::validate($file, ['png', 'jpg', 'jpeg'], 1048576);
        if ($problem !== null) {
            $this->fail('logo', $problem, $back);
        }
        $stored = Uploads::store($file, 'logos');
        if (!ImageSanitizer::clean(Uploads::absolute($stored['path']), $stored['ext'])) {
            Uploads::delete($stored['path']);
            $this->fail('logo', t('That image could not be read. Try saving it again as a PNG or JPG.'), $back);
        }
        Uploads::delete($current ?: null);
        Db::update('organizations', ['logo_path' => $stored['path'], 'updated_at' => Db::now()], ['id' => $o]);
        Audit::log('settings.logo_updated');
        $this->flash('success', t('Logo updated.'));
        return $this->redirect($back);
    }

    public function integrations(): Response
    {
        return $this->view('admin/integrations', [
            'title' => t('Integrations and system checks'),
            'crumbs' => [[t('School settings'), '/admin/settings'], [t('Integrations'), null]],
            'checks' => SystemCheck::run(),
            'ai' => [
                'label' => Providers::label(),
                'provider' => (string) Config::get('ai.provider', 'demo'),
                'model' => (string) Config::get('ai.model', ''),
                'effort' => (string) Config::get('ai.effort', 'medium'),
                'fallbacks' => (string) Config::get('ai.fallbacks', 'default'),
                'timeout' => (int) Config::get('ai.timeout', 90),
                'has_key' => (string) Config::get('ai.api_key', '') !== '',
                'base_url_host' => (string) parse_url((string) Config::get('ai.base_url', ''), PHP_URL_HOST),
            ],
        ]);
    }
}
