<?php

namespace App\Http\Controllers\Investor;

use App\Exceptions\InsufficientFunds;
use App\Exceptions\PledgeRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\PledgeRequest;
use App\Models\Campaign;
use App\Services\PledgeService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Pledge to a campaign from the wallet (PB-19). The route is protected by
 * ->can('pledge', 'campaign') (verified, risk acknowledgement, campaign open, Shariah-only);
 * PledgeService checks it all again under the campaign's lock, where a refusal becomes a message
 * next to the amount.
 */
class PledgeController extends Controller
{
    public function store(PledgeRequest $request, Campaign $campaign, PledgeService $pledges): RedirectResponse
    {
        try {
            $pledges->pledge($request->user(), $campaign, $request->cents());
        } catch (PledgeRefused $refused) {
            throw ValidationException::withMessages(['amount' => $refused->getMessage()]);
        } catch (InsufficientFunds) {
            throw ValidationException::withMessages(['amount' => 'Your wallet balance changed. Check it and try again.']);
        }

        $campaign->refresh();

        // Back to the campaign page (with its return_to). It stays open to this investor once the
        // campaign is funded, because they have pledged to it (CampaignPolicy::browse).
        return back(fallback: route('investor.campaigns.show', $campaign))->with('status', $campaign->status === Campaign::STATUS_FUNDED
            ? 'Your pledge of '.Money::format($request->cents()).' completed this campaign’s funding.'
            : 'You pledged '.Money::format($request->cents()).'. It is held in escrow until the campaign closes.');
    }
}
