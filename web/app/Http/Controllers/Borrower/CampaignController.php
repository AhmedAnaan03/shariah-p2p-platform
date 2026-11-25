<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Http\Requests\Kyc\StoreKycDocumentRequest;
use App\Models\Campaign;
use App\Models\KycDocument;
use App\Services\CampaignSubmissionService;
use App\Support\CampaignPresenter;
use App\Support\InvestorDisclosure;
use App\Support\ListFilters;
use App\Support\ReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A borrower's financing requests (PB-13, PB-14). Every route is inside the borrower portal
 * group; CampaignPolicy adds "verified to submit" and "only your own".
 */
class CampaignController extends Controller
{
    /**
     * The statuses a borrower can filter their requests by (PB-54), in the words the status
     * badges use.
     */
    private const STATUS_FILTERS = [
        Campaign::STATUS_SUBMITTED => 'Awaiting review',
        Campaign::STATUS_LIVE => 'Live',
        Campaign::STATUS_REJECTED => 'Rejected',
        Campaign::STATUS_SUSPENDED => 'Suspended',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = ListFilters::from($request, [
            'status' => array_keys(self::STATUS_FILTERS),
            'contract' => array_keys(CampaignPresenter::CONTRACT_LABELS),
        ]);
        $query = $user->campaigns()
            ->when($filters->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($filters->get('contract'), fn ($q, $contract) => $q->where('contract_type', $contract));
        $filters->applySearch($query, ['title']);

        return Inertia::render('Borrower/Campaigns/Index', [
            'campaigns' => $query->latest('id')->get()
                ->map(fn (Campaign $campaign) => [
                    ...CampaignPresenter::summary($campaign),
                    'url' => route('borrower.campaigns.show', $campaign),
                ]),
            // Whether the borrower has any request at all, so "no results for these filters"
            // and "no requests yet" can be told apart.
            'hasAny' => $user->campaigns()->exists(),
            'canCreate' => Gate::forUser($user)->allows('create', Campaign::class),
            // PB-54: the shared search-and-filter bar.
            'filters' => $filters->toArray(),
            'filterOptions' => [
                'status' => ListFilters::options(self::STATUS_FILTERS),
                'contract' => ListFilters::options(CampaignPresenter::CONTRACT_LABELS),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $permission = Gate::forUser($user)->inspect('create', Campaign::class);
        $profile = $user->borrowerProfile;

        return Inertia::render('Borrower/Campaigns/Create', [
            // An unverified borrower sees the page with the reason instead of the form.
            'canSubmit' => $permission->allowed(),
            'blockedReason' => $permission->message(),
            'isSme' => $profile?->isSme() ?? false,
            // PB-58: the verified financial profile, shown read-only; it is copied into the
            // request when it is submitted. Changing it is done in the profile.
            'finances' => $profile?->hasFinancialProfile() ? CampaignPresenter::finances($profile) : null,
            'creditHistory' => $profile?->hasFinancialProfile() ? CampaignPresenter::creditHistory($profile) : null,
            // PB-17: what investors will see on the campaign page, and what they never see.
            'investorDisclosure' => InvestorDisclosure::forBorrower($profile?->isSme() ?? false),
            'financesUrl' => route('borrower.onboarding.show', 'finances').'?'.http_build_query([ReturnUrl::PARAM => route('borrower.campaigns.create', [], false)]),
            'purposes' => self::options(Campaign::PURPOSES),
            'tenures' => array_map(fn (int $months) => ['value' => (string) $months, 'label' => "{$months} months"], Campaign::TENURES),
            'limits' => [
                'minAmount' => Campaign::MIN_AMOUNT,
                'maxAmount' => Campaign::MAX_AMOUNT,
                'maxInterestRate' => Campaign::MAX_INTEREST_RATE,
                'minProfitShare' => Campaign::MIN_PROFIT_SHARE,
                'maxProfitShare' => Campaign::MAX_PROFIT_SHARE,
            ],
            'maxBytes' => KycDocument::MAX_KILOBYTES * 1024,
            'tooLargeMessage' => StoreKycDocumentRequest::TOO_LARGE,
        ]);
    }

    public function store(StoreCampaignRequest $request, CampaignSubmissionService $submissions): RedirectResponse
    {
        $campaign = $submissions->submit($request->user(), $request->validated(), $request->file('credit_report'));

        return to_route('borrower.campaigns.show', $campaign)
            ->with('status', 'Your request has been submitted. An administrator will review it before it is shown to investors.');
    }

    public function show(Request $request, Campaign $campaign): Response
    {
        // Someone else's request answers 404 (CampaignPolicy::view).
        Gate::authorize('view', $campaign);

        $campaign->load(['financials', 'creditReport']);

        return Inertia::render('Borrower/Campaigns/Show', [
            'campaign' => CampaignPresenter::details($campaign),
            'finances' => CampaignPresenter::finances($campaign->financials),
            'creditHistory' => CampaignPresenter::creditHistory($campaign->financials),
            'creditReport' => $campaign->creditReport?->toListItem(),
            // PB-54: back to the (filtered) list the borrower came from.
            'back' => ReturnUrl::back($request, 'borrower.campaigns.index'),
        ]);
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $labels): array
    {
        return array_map(fn ($value, $label) => ['value' => $value, 'label' => $label], array_keys($labels), $labels);
    }
}
