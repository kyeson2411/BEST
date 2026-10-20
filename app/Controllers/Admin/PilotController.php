<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Response;
use Sibol\Services\PilotReport;

/** The weekly pilot report: school-wide counts per week, as a page and as CSV. No rankings, no lists of people. */
final class PilotController extends Controller
{
    public function index(): Response
    {
        $weeks = $this->weeks();
        return $this->view('admin/pilot_report', [
            'title' => t('Weekly pilot report'),
            'weeks' => $weeks,
            'rows' => PilotReport::rows($this->orgId(), $weeks),
        ]);
    }

    public function export(): Response
    {
        $weeks = $this->weeks();
        $csv = PilotReport::csv(PilotReport::rows($this->orgId(), $weeks));
        Audit::log('pilot.report_exported', null, null, ['format' => 'csv', 'weeks' => $weeks]);
        return Response::html($csv)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="sibol-pilot-report-' . gmdate('Y-m-d') . '.csv"')
            ->header('Cache-Control', 'private, no-store');
    }

    private function weeks(): int
    {
        $weeks = $this->request->int('weeks');
        return in_array($weeks, PilotReport::WEEK_CHOICES, true) ? $weeks : 8;
    }
}
