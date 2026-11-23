<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Support\CampaignPresenter;
use App\Support\ListFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The marketplace, in its first, minimal form (PB-15): every approved campaign that is still
 * open, with its funding deadline. PB-16 adds search, filters and pages; PB-17 the campaign
 * page; PB-31 hides non-Shariah campaigns for Shariah-only investors.
 */
class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = ListFilters::from($request, [
            'contract' => array_keys(CampaignPresenter::CONTRACT_LABELS),
            'purpose' => array_keys(Campaign::PURPOSES),
        ]);
        $query = Campaign::listed()->with('borrower.borrowerProfile')
            ->when($filters->get('contract'), fn ($q, $contract) => $q->where('contract_type', $contract))
            ->when($filters->get('purpose'), fn ($q, $purpose) => $q->where('purpose', $purpose));
        // An individual borrower's name is never shown to investors, so it isn't searched either.
        $filters->applySearch($query, ['title', 'borrower.borrowerProfile.business_name']);

        // inspect() runs the 'pledge' gate and returns its answer with the reason, without
        // throwing, so the marketplace can say why pledging is still locked (PB-57: shown here,
        // where it matters, not on the overview).
        $pledge = Gate::forUser($request->user())->inspect('pledge');

        return Inertia::render('Investor/Campaigns/Index', [
            'pledge' => ['allowed' => $pledge->allowed(), 'reason' => $pledge->message()],
            // Closest deadline first, so campaigns about to close are seen.
            'campaigns' => $query->orderBy('funding_deadline')->get()
                ->map(fn (Campaign $campaign) => [
                    ...CampaignPresenter::summary($campaign),
                    // An SME's business name, or nothing for an individual: investors don't see
                    // an individual borrower's name.
                    'business_name' => $campaign->borrower->borrowerProfile?->business_name,
                ]),
            'hasAny' => Campaign::listed()->exists(),
            // PB-54: the shared search-and-filter bar. PB-16 adds sector, amount, tenure and
            // risk grade filters to the same bar.
            'filters' => $filters->toArray(),
            'filterOptions' => [
                'contract' => ListFilters::options(CampaignPresenter::CONTRACT_LABELS),
                'purpose' => ListFilters::options(Campaign::PURPOSES),
            ],
        ]);
    }
}
