<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Paginator;
use Sibol\Core\Response;
use Sibol\Core\View;

final class NotificationController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        $filter = $this->request->str('filter') === 'unread' ? 'unread' : 'all';
        $where = 'user_id = :u' . ($filter === 'unread' ? ' AND read_at IS NULL' : '');
        $total = (int) Db::value("SELECT COUNT(*) FROM notifications WHERE $where", ['u' => (int) $user['id']]);
        $pager = new Paginator($total, $this->page(), 30);
        $rows = Db::all("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC, id DESC" . $pager->limitSql(), ['u' => (int) $user['id']]);
        return $this->view('shared/notifications', [
            'title' => t('Notifications'),
            'rows' => $rows,
            'pager' => $pager,
            'filter' => $filter,
            'unread' => (int) Db::value('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL', ['u' => (int) $user['id']]),
        ]);
    }

    /**
     * The unread count for the bell's live badge, and nothing else. app.js polls it every 90 seconds
     * while the tab is visible, as a background request that does not count as activity.
     */
    public function count(): Response
    {
        $user = $this->user();
        return $this->json(['count' => (int) Db::value('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL', ['u' => (int) $user['id']])]);
    }

    /** HTML fragment for the bell popover. */
    public function panel(): Response
    {
        $user = $this->user();
        $rows = Db::all('SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC, id DESC LIMIT 8', ['u' => (int) $user['id']]);
        $unread = (int) Db::value('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL', ['u' => (int) $user['id']]);
        return Response::html(View::file('shared/notification_panel', ['rows' => $rows, 'unread' => $unread]));
    }

    /** Marks one notification read and follows its link. */
    public function open(int $id): Response
    {
        $user = $this->user();
        $row = Db::one('SELECT * FROM notifications WHERE id = :id AND user_id = :u', ['id' => $id, 'u' => (int) $user['id']]);
        if (!$row) {
            throw HttpException::notFound();
        }
        if ($row['read_at'] === null) {
            Db::update('notifications', ['read_at' => Db::now()], ['id' => $id]);
        }
        $link = (string) ($row['link'] ?? '');
        $safe = $link !== '' && str_starts_with($link, '/') && !str_starts_with($link, '//');
        return $this->redirect($safe ? $link : '/notifications');
    }

    public function readAll(): Response
    {
        $user = $this->user();
        $count = Db::exec('UPDATE notifications SET read_at = :now WHERE user_id = :u AND read_at IS NULL', ['now' => Db::now(), 'u' => (int) $user['id']]);
        if ($this->request->wantsJson()) {
            return $this->json(['updated' => $count]);
        }
        $this->flash('success', $count ? tp(':count notification marked as read.', ':count notifications marked as read.', $count) : t('You have no unread notifications.'));
        return $this->redirect('/notifications');
    }
}
