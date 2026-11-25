<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\User;
use App\Notifications\FundingOutcome;
use App\Support\Money;

/**
 * Sends the funding outcome to the borrower and each investor (PB-20), once per investor with
 * their own total, after the money has moved.
 */
class FundingNotifier
{
    public function funded(Campaign $campaign): void
    {
        $campaign->borrower->notify(new FundingOutcome($campaign, FundingOutcome::FUNDED_BORROWER));

        $totals = Pledge::where('campaign_id', $campaign->id)->where('status', 'held')
            ->selectRaw('investor_id, SUM(amount) as total')->groupBy('investor_id')->pluck('total', 'investor_id');
        foreach (User::whereKey($totals->keys())->get() as $investor) {
            $investor->notify(new FundingOutcome($campaign, FundingOutcome::FUNDED_INVESTOR, Money::fromDatabase($totals[$investor->id])));
        }
    }

    /**
     * @param  array<int, int>  $refunds  investor id => refunded cents
     */
    public function notFunded(Campaign $campaign, array $refunds): void
    {
        $campaign->borrower->notify(new FundingOutcome($campaign, FundingOutcome::FAILED_BORROWER));

        foreach (User::whereKey(array_keys($refunds))->get() as $investor) {
            $investor->notify(new FundingOutcome($campaign, FundingOutcome::REFUNDED_INVESTOR, $refunds[$investor->id]));
        }
    }
}
