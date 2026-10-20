<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Dates;
use Sibol\Core\Db;
use Sibol\Core\Paginator;
use Sibol\Core\Response;
use Sibol\Services\AuditLabels;

/** Read-only audit trail for the administrator's school. */
final class AuditController extends Controller
{
    public function index(): Response
    {
        $o = $this->orgId();
        $group = $this->request->str('group');
        $userId = $this->request->int('user');
        $from = $this->request->str('from');
        $to = $this->request->str('to');
        $where = ['a.organization_id = :o'];
        $params = ['o' => $o];
        if (isset(AuditLabels::groups()[$group])) {
            if ($group === 'user') {
                $where[] = "(a.action LIKE 'user.%' OR a.action LIKE 'users.%')";
            } elseif ($group === 'submission') {
                $where[] = "(a.action LIKE 'submission.%' OR a.action LIKE 'quiz.%' OR a.action LIKE 'gradebook.%')";
            } elseif ($group === 'class') {
                $where[] = "(a.action LIKE 'class.%' OR a.action LIKE 'subject.%' OR a.action LIKE 'period.%' OR a.action LIKE 'announcement.%')";
            } else {
                $where[] = 'a.action LIKE :g';
                $params['g'] = $group . '.%';
            }
        }
        $person = null;
        if ($userId) {
            $person = Db::one('SELECT id, given_name, family_name, preferred_name FROM users WHERE id = :id AND organization_id = :o', ['id' => $userId, 'o' => $o]);
            if ($person) {
                $where[] = "(a.actor_id = :u1 OR (a.target_type = 'user' AND a.target_id = :u2))";
                $params += ['u1' => $userId, 'u2' => $userId];
            }
        }
        if ($from !== '' && ($fromUtc = Dates::toUtc($from, '00:00'))) {
            $where[] = 'a.created_at >= :from';
            $params['from'] = $fromUtc;
        }
        if ($to !== '' && ($toUtc = Dates::toUtc($to, '23:59'))) {
            $where[] = 'a.created_at <= :to';
            $params['to'] = gmdate('Y-m-d H:i:s', strtotime($toUtc . ' UTC') + 59);
        }
        $sql = ' FROM audit_logs a WHERE ' . implode(' AND ', $where);
        $pager = new Paginator((int) Db::value('SELECT COUNT(*)' . $sql, $params), $this->page(), 50);
        $rows = Db::all(
            'SELECT a.*, u.given_name, u.family_name, u.preferred_name FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id'
            . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY a.created_at DESC, a.id DESC' . $pager->limitSql(),
            $params
        );
        return $this->view('admin/audit', [
            'title' => t('Audit log'),
            'rows' => $rows,
            'targets' => AuditLabels::targets($rows, $o),
            'pager' => $pager,
            'group' => $group,
            'person' => $person,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
