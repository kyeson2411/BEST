<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Services\Notifier;
use Sibol\Services\Privacy;
use Sibol\Services\Settings;

/**
 * Privacy requests (export, correction, deletion) and reported companion answers.
 * Administrators see a reported answer only because someone reported it; they never
 * browse conversations.
 */
final class PrivacyController extends Controller
{
    public function index(): Response
    {
        $o = $this->orgId();
        $show = $this->request->str('show') === 'closed' ? 'closed' : 'open';
        $requestStatus = $show === 'open' ? "('submitted','in_review')" : "('completed','rejected')";
        $reportStatus = $show === 'open' ? "('open')" : "('resolved','dismissed')";
        return $this->view('admin/privacy', [
            'title' => t('Privacy and safety'),
            'show' => $show,
            'requests' => Db::all(
                "SELECT r.*, u.given_name, u.family_name, u.preferred_name, u.role, u.anonymized_at
                 FROM privacy_requests r JOIN users u ON u.id = r.user_id
                 WHERE r.organization_id = :o AND r.status IN $requestStatus ORDER BY r.created_at " . ($show === 'open' ? 'ASC' : 'DESC') . ' LIMIT 100',
                ['o' => $o]
            ),
            'reports' => Db::all(
                "SELECT r.id, r.reason, r.status, r.created_at, r.reviewed_at, ru.role AS reporter_role, c.mode
                 FROM ai_reports r LEFT JOIN users ru ON ru.id = r.reporter_id
                 LEFT JOIN ai_messages m ON m.id = r.message_id LEFT JOIN ai_conversations c ON c.id = m.conversation_id
                 WHERE r.organization_id = :o AND r.status IN $reportStatus ORDER BY r.created_at " . ($show === 'open' ? 'ASC' : 'DESC') . ' LIMIT 100',
                ['o' => $o]
            ),
            'retention' => (int) Settings::get($o, 'privacy.ai_retention_days', 365),
        ]);
    }

    public function show(int $id): Response
    {
        $o = $this->orgId();
        $request = $this->findRequest($id, $o);
        $person = Db::one('SELECT * FROM users WHERE id = :u', ['u' => (int) $request['user_id']]);
        $counts = Db::one(
            "SELECT (SELECT COUNT(*) FROM ai_conversations WHERE user_id = :u1) AS conversations,
                    (SELECT COUNT(*) FROM submissions WHERE student_id = :u2) AS submissions,
                    (SELECT COUNT(*) FROM practice_sets WHERE user_id = :u3) AS practice,
                    (SELECT COUNT(*) FROM study_sessions WHERE user_id = :u4) AS sessions,
                    (SELECT COUNT(*) FROM shared_excerpts WHERE student_id = :u5) AS shares",
            ['u1' => (int) $person['id'], 'u2' => (int) $person['id'], 'u3' => (int) $person['id'], 'u4' => (int) $person['id'], 'u5' => (int) $person['id']]
        );
        $history = Db::all(
            "SELECT a.*, u.given_name, u.family_name, u.preferred_name FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id
             WHERE a.organization_id = :o AND a.target_type = 'privacy_request' AND a.target_id = :id ORDER BY a.created_at",
            ['o' => $o, 'id' => $id]
        );
        return $this->view('admin/privacy_request', [
            'title' => t('Privacy request #:id', ['id' => $id]),
            'crumbs' => [[t('Privacy and safety'), '/admin/privacy'], [t('Request #:id', ['id' => $id]), null]],
            'request' => $request,
            'person' => $person,
            'counts' => $counts,
            'history' => $history,
            'isSelf' => (int) $person['id'] === (int) $this->user()['id'],
        ]);
    }

    public function update(int $id): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $request = $this->findRequest($id, $o);
        $back = '/admin/privacy/requests/' . $id;
        $data = $this->validate([
            'status' => 'required|in:in_review,completed,rejected',
            'resolution_note' => 'nullable|string|max:2000',
        ], ['status' => t('Status'), 'resolution_note' => t('Note to the requester')], $back);
        if ($data['status'] === 'rejected' && trim((string) $data['resolution_note']) === '') {
            $this->fail('resolution_note', t('Explain why the request was declined. The requester will see this note.'), $back);
        }
        if ($data['status'] === 'completed' && $request['request_type'] === 'export' && !$request['export_path']) {
            $this->fail('status', t('Prepare the export before marking this request complete.'), $back);
        }
        $closing = in_array($data['status'], ['completed', 'rejected'], true);
        Db::update('privacy_requests', [
            'status' => $data['status'],
            'resolution_note' => $data['resolution_note'],
            'reviewer_id' => (int) $admin['id'],
            'resolved_at' => $closing ? Db::now() : null,
            'updated_at' => Db::now(),
        ], ['id' => $id]);
        Audit::log('privacy.request_updated', 'privacy_request', $id, ['status' => $data['status']]);
        if ($closing) {
            $this->notifyRequester($request, $data['status'] === 'completed' ? t('Your privacy request was completed') : t('Your privacy request was declined'));
        }
        $this->flash('success', t('Request updated.'));
        return $this->redirect($back);
    }

    public function export(int $id): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $request = $this->findRequest($id, $o);
        $back = '/admin/privacy/requests/' . $id;
        if ($request['request_type'] !== 'export' || in_array($request['status'], ['completed', 'rejected'], true)) {
            $this->flash('error', t('An export can only be prepared for an open export request.'));
            return $this->redirect($back);
        }
        $path = Privacy::export((int) $request['user_id']);
        Db::update('privacy_requests', [
            'export_path' => $path,
            'export_expires_at' => gmdate('Y-m-d H:i:s', time() + Privacy::EXPORT_DAYS * 86400),
            'status' => 'completed',
            'reviewer_id' => (int) $admin['id'],
            'resolved_at' => Db::now(),
            'updated_at' => Db::now(),
        ], ['id' => $id]);
        Audit::log('privacy.export_created', 'privacy_request', $id);
        $this->notifyRequester($request, t('Your data export is ready'), t('Download it from your account settings within :days days.', ['days' => Privacy::EXPORT_DAYS]));
        $this->flash('success', t('Export prepared. The requester can download it from their account settings for :days days; administrators cannot download it.', ['days' => Privacy::EXPORT_DAYS]));
        return $this->redirect($back);
    }

    public function erase(int $id): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $request = $this->findRequest($id, $o);
        $back = '/admin/privacy/requests/' . $id;
        if ($request['request_type'] !== 'deletion' || in_array($request['status'], ['completed', 'rejected'], true)) {
            $this->flash('error', t('Erasure is only available for an open deletion request.'));
            return $this->redirect($back);
        }
        $person = Db::one('SELECT * FROM users WHERE id = :u AND organization_id = :o', ['u' => (int) $request['user_id'], 'o' => $o]);
        if (!$person || (int) $person['id'] === (int) $admin['id']) {
            $this->flash('error', t('Another administrator must handle a deletion request for your own account.'));
            return $this->redirect($back);
        }
        if ($person['role'] === 'admin' && (int) Db::value("SELECT COUNT(*) FROM users WHERE organization_id = :o AND role = 'admin' AND status = 'active'", ['o' => $o]) <= 1) {
            $this->flash('error', t('The school needs at least one active administrator.'));
            return $this->redirect($back);
        }
        if (mb_strtolower(trim($this->request->str('confirm_name'))) !== mb_strtolower(user_name($person))) {
            $this->fail('confirm_name', t('Type the person\'s full name exactly as shown to confirm.'), $back);
        }
        $includeRecords = $this->request->bool('include_records');
        // No notification: the account is anonymised and signed out, and its notifications are erased too.
        $counts = Privacy::erase((int) $person['id'], $includeRecords);
        Db::update('privacy_requests', [
            'status' => 'completed',
            'reviewer_id' => (int) $admin['id'],
            'resolution_note' => trim((string) $this->request->str('resolution_note')) ?: null,
            'resolved_at' => Db::now(),
            'updated_at' => Db::now(),
            'details' => null,
        ], ['id' => $id]);
        Audit::log('privacy.erased', 'privacy_request', $id, $counts + ['records_included' => $includeRecords ? 1 : 0]);
        $this->flash('success', t('Personal data erased and the account anonymised.'));
        return $this->redirect($back);
    }

    public function report(int $id): Response
    {
        $o = $this->orgId();
        $report = $this->findReport($id, $o);
        if ($report['status'] === 'open') {
            Audit::log('ai.report_viewed', 'ai_report', $id);
        }
        return $this->view('admin/privacy_report', [
            'title' => t('Reported answer #:id', ['id' => $id]),
            'crumbs' => [[t('Privacy and safety'), '/admin/privacy#reports'], [t('Report #:id', ['id' => $id]), null]],
            'report' => $report,
        ]);
    }

    public function resolveReport(int $id): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $report = $this->findReport($id, $o);
        $back = '/admin/privacy/reports/' . $id;
        $data = $this->validate([
            'status' => 'required|in:resolved,dismissed',
            'resolution_note' => 'nullable|string|max:500',
        ], ['status' => t('Outcome'), 'resolution_note' => t('Note')], $back);
        Db::update('ai_reports', [
            'status' => $data['status'],
            'resolution_note' => $data['resolution_note'],
            'reviewed_by' => (int) $admin['id'],
            'reviewed_at' => Db::now(),
        ], ['id' => $id]);
        Audit::log('ai.report_resolved', 'ai_report', $id, ['status' => $data['status']]);
        if ($report['reporter_id']) {
            Notifier::send($o, [(int) $report['reporter_id']], 'privacy', t('The answer you reported was reviewed'), $data['resolution_note'] ?: t('Thank you for helping keep the companion accurate and safe.'), null);
        }
        $this->flash('success', t('Report closed.'));
        return $this->redirect('/admin/privacy#reports');
    }

    // ------------------------------------------------------------------------------------------

    private function findRequest(int $id, int $o): array
    {
        $request = Db::one('SELECT * FROM privacy_requests WHERE id = :id AND organization_id = :o', ['id' => $id, 'o' => $o]);
        if (!$request) {
            throw HttpException::notFound();
        }
        return $request;
    }

    /** The reported answer and the reporter's note only; never the rest of the conversation. */
    private function findReport(int $id, int $o): array
    {
        $report = Db::one(
            'SELECT r.*, ru.given_name, ru.family_name, ru.preferred_name, ru.role AS reporter_role,
                    c.mode, c.class_id, cl.name AS class_name, m.grounding, m.is_demo, m.provider, m.model, m.created_at AS answered_at,
                    rv.given_name AS rv_given, rv.family_name AS rv_family, rv.preferred_name AS rv_preferred
             FROM ai_reports r LEFT JOIN users ru ON ru.id = r.reporter_id
             LEFT JOIN ai_messages m ON m.id = r.message_id LEFT JOIN ai_conversations c ON c.id = m.conversation_id
             LEFT JOIN classes cl ON cl.id = c.class_id LEFT JOIN users rv ON rv.id = r.reviewed_by
             WHERE r.id = :id AND r.organization_id = :o',
            ['id' => $id, 'o' => $o]
        );
        if (!$report) {
            throw HttpException::notFound();
        }
        $report['citations'] = $report['message_id']
            ? Db::all('SELECT ref_no, title, page_from, section_title FROM ai_citations WHERE message_id = :m ORDER BY ref_no', ['m' => (int) $report['message_id']])
            : [];
        return $report;
    }

    private function notifyRequester(array $request, string $title, ?string $body = null): void
    {
        Notifier::send((int) $request['organization_id'], [(int) $request['user_id']], 'privacy', $title, $body, '/settings#privacy');
    }
}
