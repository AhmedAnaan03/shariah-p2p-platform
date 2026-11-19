<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Support\CampaignPresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The marketplace, in its first, minimal form (PB-15): every approved campaign that is still
 * open, with its funding deadline. PB-16 adds search, filters and pages; PB-17 the campaign
 * page; PB-31 hides non-Shariah campaigns for Shariah-only investors.
 */
class CampaignController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Investor/Campaigns/Index', [
            // Closest deadline first, so campaigns about to close are seen.
            'campaigns' => Campaign::listed()->with('borrower.borrowerProfile')->orderBy('funding_deadline')->get()
                ->map(fn (Campaign $campaign) => [
                    ...CampaignPresenter::summary($campaign),
                    // An SME's business name, or nothing for an individual: investors don't see
                    // an individual borrower's name.
                    'business_name' => $campaign->borrower->borrowerProfile?->business_name,
                ]),
        ]);
    }
}
