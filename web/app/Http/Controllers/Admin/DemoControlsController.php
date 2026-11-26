<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ReportRefused;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\DemoClock;
use App\Services\DemoReports;
use App\Support\CampaignPresenter;
use App\Support\ListFilters;
use App\Support\Money;
use App\Support\SchedulePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * DEMO TOOL, local environment only (PB-34): the admin "Demo controls" page. Lists repaying
 * campaigns and moves a schedule so its next row is due now or overdue (DemoClock).
 *
 * The routes are only registered when APP_ENV is local (routes/web.php), so they don't exist in
 * production or the tests; the check here is a second lock on the same door.
 */
class DemoControlsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(app()->environment('local'), 404);

        // PB-54: the shared search-and-filter bar, like every list.
        $filters = ListFilters::from($request, ['contract' => array_keys(CampaignPresenter::CONTRACT_LABELS)]);
        $query = Campaign::where('status', Campaign::STATUS_REPAYING)
            ->when($filters->get('contract'), fn ($q, $contract) => $q->where('contract_type', $contract));
        $filters->applySearch($query, ['title']);
        $campaigns = $query->orderBy('disbursed_at')->get();

        return Inertia::render('Admin/DemoControls', [
            'hasAny' => Campaign::where('status', Campaign::STATUS_REPAYING)->exists(),
            'filters' => $filters->toArray(),
            'filterOptions' => ['contract' => ListFilters::options(CampaignPresenter::CONTRACT_LABELS)],
            'campaigns' => $campaigns->map(function (Campaign $campaign) {
                $schedule = SchedulePresenter::forBorrower($campaign);
                $next = collect($schedule['rows'] ?? [])->firstWhere('state', '!=', SchedulePresenter::STATE_PAID);

                return [
                    'id' => $campaign->id,
                    'title' => $campaign->title,
                    'contract_type' => $campaign->contract_type,
                    'contract_label' => CampaignPresenter::CONTRACT_LABELS[$campaign->contract_type],
                    'badge_status' => CampaignPresenter::badgeStatus($campaign),
                    'next' => $next ? ['label' => $next['label'], 'dateLabel' => $next['dateLabel'], 'stateLabel' => $next['stateLabel']] : null,
                    'url' => route('admin.demo.advance', $campaign),
                    // PB-29: a Mudarabah can get a sample business report for its next period.
                    'sampleReportUrl' => $campaign->isMudarabah() ? route('admin.demo.sample-report', $campaign) : null,
                    'reviewUrl' => route('admin.campaigns.show', $campaign),
                ];
            })->values(),
            'defaultOverdueDays' => DemoClock::DEFAULT_OVERDUE_DAYS,
        ]);
    }

    public function advance(Request $request, Campaign $campaign, DemoClock $clock): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $data = $request->validate([
            'mode' => ['required', 'in:due,overdue'],
        ]);

        try {
            $moved = $clock->advance($campaign, $data['mode'] === 'overdue' ? DemoClock::DEFAULT_OVERDUE_DAYS : 0, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('notice', $e->getMessage());
        }

        // PB-35: "overdue" marks the instalment late at once (DemoClock), not a minute later.
        // "dated", not "due": the same words fit a Mudarabah's planned capital return.
        $what = $data['mode'] === 'overdue' ? 'dated '.DemoClock::DEFAULT_OVERDUE_DAYS.' days ago' : 'dated today';

        return back()->with('status', "Demo: row {$moved['next_no']} of \"{$campaign->title}\" is now {$what}.");
    }

    /**
     * PB-29: submit a sample business report (a profit or a loss) for a Mudarabah's next period.
     */
    public function sampleReport(Request $request, Campaign $campaign, DemoReports $demo): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $data = $request->validate([
            'outcome' => ['required', 'in:'.DemoReports::PROFIT.','.DemoReports::LOSS],
        ]);

        try {
            $result = $demo->submitSample($campaign, $data['outcome'], $request->user());
        } catch (InvalidArgumentException|ReportRefused $e) {
            return back()->with('notice', $e->getMessage());
        }

        $report = $result['report'];
        $notes = array_filter([
            $result['moved'] ? 'the period was moved to today' : null,
            $result['toppedUp'] > 0 ? 'the business’s wallet was topped up with '.Money::format($result['toppedUp']).' of simulated money' : null,
        ]);

        return to_route('admin.reports.show', $report)->with('status', "Demo: a sample {$data['outcome']} report for period {$report->repaymentSchedule->instalment_no} of \"{$campaign->title}\" was submitted"
            .($notes === [] ? '' : ' ('.implode('; ', $notes).')').'. Decide it below.');
    }
}
