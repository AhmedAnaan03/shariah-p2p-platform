<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Policies\CampaignPolicy;
use App\Support\CampaignPresenter;
use App\Support\ListFilters;
use App\Support\MarketplaceFilters;
use App\Support\RepaymentHistory;
use App\Support\ReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The marketplace (PB-15, extended in PB-16) and a campaign's page (PB-17).
 *
 * Both start from Campaign::visibleTo(), which lists only open campaigns and, while the investor
 * has Shariah-only on, only Murabahah and Mudarabah (PB-31). The campaign page also checks the
 * 'browse' policy, so a hidden campaign can't be opened by typing its address.
 */
class CampaignController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $shariahOnly = (bool) $user->investorProfile?->shariah_only;

        $filters = ListFilters::from($request, MarketplaceFilters::allowed($shariahOnly));
        $query = Campaign::visibleTo($user)->withFunding()->with(['borrower.borrowerProfile', 'currentRiskScore']);
        MarketplaceFilters::apply($query, $filters);
        // An individual borrower's name is never shown to investors, so it isn't searched either.
        $filters->applySearch($query, ['title', 'borrower.borrowerProfile.business_name']);

        // Closest deadline first, so campaigns about to close are seen; id keeps pages stable
        // when two deadlines are the same.
        $page = $query->orderBy('funding_deadline')->orderBy('id')
            ->paginate(MarketplaceFilters::PER_PAGE)->withQueryString();

        // A page past the end (an old link, or fewer results since): go to the last page.
        if ($page->isEmpty() && $page->currentPage() > 1 && $page->lastPage() >= 1) {
            return redirect()->to($page->url($page->lastPage()));
        }

        // inspect() runs the 'pledge' gate and returns its answer with the reason, without
        // throwing, so the marketplace can say why pledging is still locked (PB-57).
        $pledge = Gate::forUser($user)->inspect('pledge');

        return Inertia::render('Investor/Campaigns/Index', [
            'pledge' => ['allowed' => $pledge->allowed(), 'reason' => $pledge->message()],
            'campaigns' => self::paginated($page),
            'hasAny' => Campaign::visibleTo($user)->exists(),
            'shariahOnly' => $shariahOnly,
            'filters' => $filters->toArray(),
            'filterOptions' => MarketplaceFilters::options($shariahOnly),
        ]);
    }

    public function show(Request $request, Campaign $campaign): Response|RedirectResponse
    {
        $access = Gate::inspect('browse', $campaign);
        // PB-31: a conventional campaign while Shariah-only is on. Back to the marketplace with
        // the reason, rather than an error page (also where the mode switch lands, if it is
        // turned on while this page is open).
        if ($access->code() === CampaignPolicy::HIDDEN_BY_SHARIAH_ONLY) {
            // Keep a message from the request before (the switch's "Shariah-only mode is on.").
            $request->session()->reflash();

            return to_route('investor.campaigns.index')->with('notice', $access->message());
        }
        $access->authorize(); // 404 for a campaign that isn't open

        $campaign = Campaign::whereKey($campaign->id)->withFunding()
            ->with(['borrower.borrowerProfile', 'currentRiskScore', 'financials'])
            ->firstOrFail();
        $pledge = Gate::inspect('pledge', $campaign);
        $score = $campaign->currentRiskScore;

        return Inertia::render('Investor/Campaigns/Show', [
            'campaign' => CampaignPresenter::forInvestor($campaign),
            // The borrower's own credit history answers, from the request's snapshot (PB-14),
            // and how they have repaid earlier campaigns here.
            'creditHistory' => $campaign->financials ? CampaignPresenter::creditHistory($campaign->financials) : null,
            'repaymentHistory' => RepaymentHistory::for($campaign->borrower, $campaign),
            // Reserved for the AI-assisted grade and its explanation (PB-25, PB-26).
            'risk' => $score ? [
                'grade' => $score->grade,
                'probability' => round((float) $score->probability_of_default * 100, 1),
                'model' => $score->model_version,
            ] : null,
            'pledge' => ['allowed' => $pledge->allowed(), 'reason' => $pledge->message()],
            // PB-54: back to the marketplace with its filters and page.
            'back' => ReturnUrl::back($request, 'investor.campaigns.index'),
        ]);
    }

    /**
     * The page of cards and what the Pagination component needs, without the paginator's
     * HTML labels.
     *
     * @return array<string, mixed>
     */
    private static function paginated(LengthAwarePaginator $page): array
    {
        return [
            'data' => $page->getCollection()->map(fn (Campaign $campaign) => CampaignPresenter::card($campaign))->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
            'prev_url' => $page->previousPageUrl(),
            'next_url' => $page->nextPageUrl(),
            // Page numbers with gaps ("1 2 3 … 9"): one on each side of the current page.
            'pages' => collect($page->onEachSide(1)->linkCollection())
                ->slice(1, -1) // drop the paginator's own Previous and Next
                ->map(fn (array $link) => [
                    'label' => $link['label'],
                    'url' => $link['url'],
                    'active' => $link['active'],
                ])->values(),
        ];
    }
}
