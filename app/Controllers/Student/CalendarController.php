<?php

declare(strict_types=1);

namespace Sibol\Controllers\Student;

use DateTimeImmutable;
use Sibol\Controllers\Controller;
use Sibol\Core\Dates;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Policies\Access;

/** Month calendar of due dates, scheduled openings and personal study sessions, in the student's timezone. */
final class CalendarController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        $monthParam = $this->request->str('month');
        $now = Dates::now();
        $first = preg_match('/^\d{4}-\d{2}$/', $monthParam)
            ? (DateTimeImmutable::createFromFormat('!Y-m', $monthParam, Dates::zone()) ?: $now->modify('first day of this month'))
            : $now->modify('first day of this month')->setTime(0, 0);
        $gridStart = $first->modify('-' . (((int) $first->format('N')) - 1) . ' days')->setTime(0, 0);
        $gridEnd = $gridStart->modify('+42 days');
        $utc = new \DateTimeZone('UTC');
        $from = $gridStart->setTimezone($utc)->format('Y-m-d H:i:s');
        $to = $gridEnd->setTimezone($utc)->format('Y-m-d H:i:s');
        $uid = (int) $user['id'];

        $events = [];
        foreach (Db::all(
            "SELECT a.id, a.title, a.kind, a.due_at, c.name AS class_name,
                    EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = :u1 AND s.status = 'submitted') AS done
             FROM assignments a
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = :u2 AND cm.role = 'student' AND cm.status = 'active'
             JOIN classes c ON c.id = a.class_id AND c.status = 'active'
             WHERE a.status IN ('published','closed') AND a.due_at >= :f AND a.due_at < :t",
            ['u1' => $uid, 'u2' => $uid, 'f' => $from, 't' => $to]
        ) as $a) {
            $events[] = ['type' => 'due', 'at' => $a['due_at'], 'title' => $a['title'], 'meta' => $a['class_name'], 'done' => (bool) $a['done'], 'url' => '/student/assignments/' . (int) $a['id'], 'id' => (int) $a['id']];
        }
        foreach (Db::all(
            "SELECT a.id, a.title, a.opens_at, c.name AS class_name FROM assignments a
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = :u AND cm.role = 'student' AND cm.status = 'active'
             JOIN classes c ON c.id = a.class_id AND c.status = 'active'
             WHERE a.status = 'published' AND a.opens_at > UTC_TIMESTAMP() AND a.opens_at >= :f AND a.opens_at < :t",
            ['u' => $uid, 'f' => $from, 't' => $to]
        ) as $a) {
            $events[] = ['type' => 'opens', 'at' => $a['opens_at'], 'title' => t('Opens: :t', ['t' => $a['title']]), 'meta' => $a['class_name'], 'done' => false, 'url' => '/student/assignments/' . (int) $a['id'], 'id' => (int) $a['id']];
        }
        foreach (Db::all(
            'SELECT ss.*, c.name AS class_name FROM study_sessions ss LEFT JOIN classes c ON c.id = ss.class_id
             WHERE ss.user_id = :u AND ss.starts_at >= :f AND ss.starts_at < :t',
            ['u' => $uid, 'f' => $from, 't' => $to]
        ) as $s) {
            $events[] = ['type' => 'study', 'at' => $s['starts_at'], 'title' => $s['title'], 'meta' => t(':n min', ['n' => (int) $s['duration_minutes']]) . ($s['class_name'] ? ', ' . $s['class_name'] : ''), 'done' => $s['completed_at'] !== null, 'url' => null, 'id' => (int) $s['id'], 'notes' => $s['notes']];
        }
        usort($events, static fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));
        $byDay = [];
        foreach ($events as $ev) {
            $byDay[Dates::local((string) $ev['at'])->format('Y-m-d')][] = $ev;
        }

        return $this->view('student/calendar', [
            'title' => t('Calendar'),
            'first' => $first,
            'gridStart' => $gridStart,
            'byDay' => $byDay,
            'events' => $events,
            'today' => $now->format('Y-m-d'),
            'classes' => array_map(static fn ($id) => Db::one('SELECT id, name FROM classes WHERE id = :id', ['id' => $id]), Access::studentClassIds($user)),
        ]);
    }

    public function store(): Response
    {
        $user = $this->user();
        $data = $this->validate([
            'title' => 'required|string|max:200',
            'date' => 'required|date',
            'time' => 'required|string|max:5',
            'duration_minutes' => 'required|int|min:5|max:480',
            'class_id' => 'nullable|int',
            'notes' => 'nullable|string|max:1000',
        ], ['title' => t('What you will study'), 'duration_minutes' => t('Length')], '/student/calendar');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $data['time'])) {
            $this->fail('time', t('Enter a time like 19:00.'), '/student/calendar');
        }
        $classId = $data['class_id'] ? (int) $data['class_id'] : null;
        if ($classId !== null && !in_array($classId, Access::studentClassIds($user), true)) {
            $classId = null;
        }
        $startsAt = Dates::toUtc($data['date'] . 'T' . $data['time']);
        Db::insert('study_sessions', [
            'organization_id' => (int) $user['organization_id'], 'user_id' => (int) $user['id'], 'class_id' => $classId,
            'title' => $data['title'], 'notes' => $data['notes'], 'starts_at' => $startsAt,
            'duration_minutes' => (int) $data['duration_minutes'], 'source' => 'manual', 'created_at' => Db::now(),
        ]);
        $this->flash('success', t('Study session added.'));
        return $this->redirect('/student/calendar?month=' . substr((string) $data['date'], 0, 7));
    }

    public function complete(int $id): Response
    {
        $session = $this->own($id);
        Db::update('study_sessions', ['completed_at' => $session['completed_at'] ? null : Db::now()], ['id' => $id]);
        $this->flash('success', $session['completed_at'] ? t('Marked as not done.') : t('Nice work. Session marked as done.'));
        return $this->redirect('/student/calendar?month=' . Dates::local((string) $session['starts_at'])->format('Y-m'));
    }

    public function destroy(int $id): Response
    {
        $session = $this->own($id);
        Db::delete('study_sessions', ['id' => $id]);
        $this->flash('success', t('Study session removed.'));
        return $this->redirect('/student/calendar?month=' . Dates::local((string) $session['starts_at'])->format('Y-m'));
    }

    /** @return array<string,mixed> */
    private function own(int $id): array
    {
        $row = Db::one('SELECT * FROM study_sessions WHERE id = :id AND user_id = :u', ['id' => $id, 'u' => (int) $this->user()['id']]);
        if (!$row) {
            throw HttpException::notFound();
        }
        return $row;
    }
}
