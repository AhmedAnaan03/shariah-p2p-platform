<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientFunds;
use App\Exceptions\ReportRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BusinessReportDecisionRequest;
use App\Models\BusinessReport;
use App\Services\BusinessReportService;
use App\Services\WalletLedger;
use App\Support\ListFilters;
use App\Support\Money;
use App\Support\MudarabahPresenter;
use App\Support\ReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The administrator's review of Mudarabah business reports (PB-29): the list (with the shared
 * search-and-filter bar, PB-54), one report with a preview of each decision, and the decision.
 * Every route is inside the admin portal group.
 */
class BusinessReportController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = ListFilters::from($request, ['status' => array_keys(MudarabahPresenter::REPORT_STATUS_LABELS)]);
        $query = BusinessReport::with(['campaign.borrower.borrowerProfile', 'repaymentSchedule'])
            ->when($filters->get('status'), fn ($q, $status) => $q->where('status', $status));
        $filters->applySearch($query, ['campaign.title', 'campaign.borrower.borrowerProfile.business_name']);

        // Waiting reports first, oldest first, so nobody waits longest; then the rest, newest first.
        $reports = $query->orderByRaw("status = 'submitted' desc")->orderByRaw("CASE WHEN status = 'submitted' THEN submitted_at END asc")
            ->orderByDesc('id')->get();

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $reports->map(fn (BusinessReport $report) => [
                ...MudarabahPresenter::reportRow($report),
                'campaignTitle' => $report->campaign->title,
                'business' => $report->campaign->borrower->borrowerProfile?->business_name ?? $report->campaign->borrower->name,
                'url' => route('admin.reports.show', $report),
            ])->values(),
            'hasAny' => BusinessReport::exists(),
            'filters' => $filters->toArray(),
            'filterOptions' => ['status' => ListFilters::options(MudarabahPresenter::REPORT_STATUS_LABELS)],
        ]);
    }

    public function show(Request $request, BusinessReport $report): Response
    {
        $report->load(['campaign.borrower.borrowerProfile', 'repaymentSchedule', 'document']);

        return Inertia::render('Admin/Reports/Show', [
            ...MudarabahPresenter::forAdmin($report),
            'decideUrl' => route('admin.reports.decide', $report),
            'causes' => collect(MudarabahPresenter::CAUSE_LABELS)->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
                'description' => MudarabahPresenter::CAUSE_DESCRIPTIONS[$value],
            ])->values(),
            'back' => ReturnUrl::back($request, 'admin.reports.index'),
        ]);
    }

    public function decide(BusinessReportDecisionRequest $request, BusinessReport $report, BusinessReportService $reports): RedirectResponse
    {
        $data = $request->validated();

        try {
            if ($data['decision'] === BusinessReportDecisionRequest::REJECT) {
                $reports->reject($request->user(), $report, $data['reason']);

                return back()->with('status', 'Report not accepted. The business has been told your reason and can submit a new one.');
            }
            $reports->approve($request->user(), $report, $data['loss_cause'] ?? null, $data['reason'] ?? null);
        } catch (ReportRefused $refused) {
            throw ValidationException::withMessages(['decision' => $refused->getMessage()]);
        } catch (InsufficientFunds) {
            $balance = app(WalletLedger::class)->balanceFor($report->campaign->borrower);
            throw ValidationException::withMessages(['decision' => 'The business’s wallet holds only '.Money::format($balance).', not enough for what this report pays the investors. Ask the business to top up, or reject the report with a reason.']);
        }

        $report->refresh();

        return back()->with('status', $report->campaign->status === 'completed'
            ? 'Report approved. The capital is settled and the campaign is complete. The business and the investors have been told.'
            : 'Report approved and the money shared out. The business and the investors have been told.');
    }
}
