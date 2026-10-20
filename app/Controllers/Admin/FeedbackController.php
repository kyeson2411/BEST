<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Policies\Access;
use Sibol\Services\SiteFeedback;

/** Feedback about Sibol from the school's students and teachers: a status for each, and one short reply. */
final class FeedbackController extends Controller
{
    public function index(): Response
    {
        $o = $this->orgId();
        $show = $this->request->str('show') === 'closed' ? 'closed' : 'open';
        $category = $this->request->str('category');
        $category = in_array($category, SiteFeedback::CATEGORIES, true) ? $category : '';
        [$in, $params] = Db::in($show === 'open' ? SiteFeedback::OPEN : array_values(array_diff(SiteFeedback::STATUSES, SiteFeedback::OPEN)), 's');
        $params['o'] = $o;
        $where = "f.organization_id = :o AND f.status IN $in";
        if ($category !== '') {
            $where .= ' AND f.category = :c';
            $params['c'] = $category;
        }
        return $this->view('admin/feedback', [
            'title' => t('Feedback'),
            'show' => $show,
            'category' => $category,
            'items' => Db::all(
                "SELECT f.id, f.user_role, f.category, f.body, f.page_path, f.status, f.reply, f.created_at,
                        u.given_name, u.family_name, u.preferred_name, u.anonymized_at
                 FROM site_feedback f LEFT JOIN users u ON u.id = f.user_id
                 WHERE $where ORDER BY f.created_at " . ($show === 'open' ? 'ASC' : 'DESC') . ' LIMIT 200',
                $params
            ),
            'counts' => Db::all("SELECT status, COUNT(*) AS n FROM site_feedback WHERE organization_id = :o GROUP BY status", ['o' => $o]),
        ]);
    }

    public function show(int $id): Response
    {
        $item = Access::schoolFeedback($this->user(), $id);
        return $this->view('admin/feedback_item', [
            'title' => SiteFeedback::categoryLabel((string) $item['category']),
            'crumbs' => [[t('Feedback'), '/admin/feedback'], ['#' . $id, null]],
            'item' => $item,
        ]);
    }

    public function update(int $id): Response
    {
        $admin = $this->user();
        $item = Access::schoolFeedback($admin, $id);
        $data = $this->validate([
            'status' => 'required|in:' . implode(',', SiteFeedback::STATUSES),
            'reply' => 'nullable|string|max:' . SiteFeedback::MAX_REPLY,
        ], ['status' => t('Status'), 'reply' => t('Reply')], '/admin/feedback/' . $id);
        $notified = SiteFeedback::update($admin, $item, (string) $data['status'], $data['reply']);
        $this->flash('success', $notified ? t('Saved. Your reply was sent to them as a notification.') : t('Saved.'));
        return $this->redirect('/admin/feedback/' . $id);
    }
}
