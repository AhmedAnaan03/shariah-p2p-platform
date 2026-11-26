<?php

namespace App\Http\Controllers\Borrower;

use App\Exceptions\ReportRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kyc\StoreKycDocumentRequest;
use App\Http\Requests\Repayment\StoreBusinessReportRequest;
use App\Models\Campaign;
use App\Models\KycDocument;
use App\Services\BusinessReportService;
use App\Services\WalletLedger;
use App\Support\Money;
use App\Support\MudarabahCapital;
use App\Support\MudarabahPresenter;
use App\Support\ReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A Mudarabah business reports a period (PB-29): the form and its submission. Only the campaign's
 * own business (404 for anyone else, CampaignPolicy::view); BusinessReportService decides, under
 * the campaign's lock, which period is next and whether it can be reported yet.
 */
class BusinessReportController extends Controller
{
    public function create(Request $request, Campaign $campaign): Response|RedirectResponse
    {
        Gate::authorize('view', $campaign);
        $period = BusinessReportService::nextPeriod($campaign, $request->user());
        if ($period['schedule'] === null || $period['refusal'] !== null) {
            return to_route('borrower.campaigns.show', $campaign)->with('notice', $period['refusal'] ?? 'There is no period to report.');
        }

        $schedule = $period['schedule'];
        $totals = MudarabahCapital::totals(MudarabahCapital::accounts($campaign));
        $final = BusinessReportService::isFinal($schedule);

        return Inertia::render('Borrower/Campaigns/ReportCreate', [
            'campaign' => ['id' => $campaign->id, 'title' => $campaign->title],
            'period' => [
                'no' => $schedule->instalment_no,
                'label' => "Period {$schedule->instalment_no}",
                'dateLabel' => $schedule->due_date->format('j M Y'),
                'plannedCapital' => (string) $schedule->capital_due,
                'final' => $final,
                // A sensible starting value: the planned amount, or on the last period all of it.
                'suggestedCapital' => Money::fromCents($final ? $totals['outstanding'] : min(Money::fromDatabase($schedule->capital_due), $totals['outstanding'])),
            ],
            'capital' => [
                'outstanding' => Money::fromCents($totals['outstanding']),
                'unrestored' => Money::fromCents($totals['unrestored']),
            ],
            'investorShareBp' => $campaign->investorShareBasisPoints(),
            'ratio' => MudarabahPresenter::ratio($campaign),
            'note' => MudarabahPresenter::RULES_NOTE,
            'balance' => Money::fromCents(app(WalletLedger::class)->balanceFor($request->user())),
            'storeUrl' => route('borrower.campaigns.reports.store', $campaign),
            'maxBytes' => KycDocument::MAX_KILOBYTES * 1024,
            'tooLargeMessage' => StoreKycDocumentRequest::TOO_LARGE,
            'back' => ReturnUrl::back($request, 'borrower.campaigns.show', ['campaign' => $campaign]),
        ]);
    }

    public function store(StoreBusinessReportRequest $request, Campaign $campaign, BusinessReportService $reports): RedirectResponse
    {
        Gate::authorize('view', $campaign);

        try {
            $report = $reports->submit($request->user(), $campaign, $request->cents(), $request->file('document'));
        } catch (ReportRefused $refused) {
            throw ValidationException::withMessages(['capital_returned' => $refused->getMessage()]);
        }

        return to_route('borrower.campaigns.show', $campaign)->with('status', "Your report for period {$report->repaymentSchedule->instalment_no} was submitted. An administrator will review it; no money moves until it is approved.");
    }
}
