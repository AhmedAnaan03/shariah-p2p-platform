<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CampaignDecisionRequest;
use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Services\CampaignReviewService;
use App\Support\CampaignPresenter;
use App\Support\RiskFeatures;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The administrator's campaign review (PB-15): requests waiting for a decision, live
 * campaigns, the review page and the decision. Every route is inside the admin portal group.
 */
class CampaignController extends Controller
{
    public function index(): Response
    {
        $row = fn (Campaign $campaign) => [
            ...CampaignPresenter::summary($campaign),
            'borrower_name' => $campaign->borrower->name,
            'url' => route('admin.campaigns.show', $campaign),
        ];

        return Inertia::render('Admin/Campaigns/Index', [
            // Oldest first, so nobody waits longest.
            'toReview' => Campaign::with('borrower')->where('status', Campaign::STATUS_SUBMITTED)
                ->oldest('submitted_at')->oldest('id')->get()->map($row),
            // Soonest deadline first.
            'live' => Campaign::with('borrower')->where('status', Campaign::STATUS_LIVE)
                ->orderBy('funding_deadline')->get()->map($row),
        ]);
    }

    public function show(Campaign $campaign): Response
    {
        $campaign->load(['borrower.borrowerProfile', 'financials', 'creditReport']);
        $borrower = $campaign->borrower;
        $profile = $borrower->borrowerProfile;
        $features = RiskFeatures::for($campaign);

        return Inertia::render('Admin/Campaigns/Show', [
            'campaign' => CampaignPresenter::details($campaign),
            'borrower' => [
                'name' => $borrower->name,
                'email' => $borrower->email,
                'status' => $borrower->status,
                'borrowing_as' => $profile ? BorrowerProfile::TYPES[$profile->borrower_type] : null,
                'business_name' => $profile?->business_name,
                'verification_url' => route('admin.verifications.show', $borrower),
            ],
            'finances' => CampaignPresenter::finances($campaign->financials),
            'creditHistory' => CampaignPresenter::creditHistory($campaign->financials),
            'creditReport' => $campaign->creditReport?->toListItem(),
            // The ratios the risk model will work from, so the admin can sanity-check them.
            'ratios' => [
                ['label' => 'Loan to income', 'value' => number_format($features['loan_to_income'], 2).' × annual income', 'mono' => true],
                ['label' => 'Monthly principal as a share of monthly income', 'value' => number_format($features['repayment_burden'] * 100, 1).'%', 'mono' => true],
                ['label' => 'Existing debt repayments as a share of income', 'value' => number_format($features['dti'], 1).'%', 'mono' => true],
            ],
            'tenureOutsideTrainingRange' => RiskFeatures::tenureOutsideTrainingRange($campaign->tenure_months),
            'decisions' => CampaignReviewService::decisionsFor($campaign),
            'fundingDays' => Campaign::FUNDING_DAYS,
            // For the approve confirmation: the deadline if approved now, as a Maldives date.
            'deadlineIfApproved' => Campaign::localDate(Campaign::fundingDeadlineFrom(now())),
            'decideUrl' => route('admin.campaigns.decide', $campaign),
        ]);
    }

    public function decide(CampaignDecisionRequest $request, Campaign $campaign, CampaignReviewService $reviews): RedirectResponse
    {
        $admin = $request->user();
        $decision = $request->validated('decision');
        $reason = $request->validated('reason');

        $campaign = match ($decision) {
            CampaignReviewService::APPROVE => $reviews->approve($campaign, $admin),
            CampaignReviewService::REJECT => $reviews->reject($campaign, $admin, $reason),
            CampaignReviewService::SUSPEND => $reviews->suspend($campaign, $admin, $reason),
        };

        $message = match ($decision) {
            CampaignReviewService::APPROVE => "\"{$campaign->title}\" is live until {$campaign->fundingDeadlineLabel()}. The borrower has been notified.",
            CampaignReviewService::REJECT => "\"{$campaign->title}\" has been rejected and the borrower notified of the reason.",
            CampaignReviewService::SUSPEND => "\"{$campaign->title}\" has been suspended and the borrower notified of the reason.",
        };

        return to_route('admin.campaigns.index')->with('status', $message);
    }
}
