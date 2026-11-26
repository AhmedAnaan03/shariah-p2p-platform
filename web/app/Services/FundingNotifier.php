<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\User;
use App\Notifications\CampaignDisbursed;
use App\Notifications\FundingOutcome;
use App\Support\Money;
use App\Support\SchedulePresenter;

/**
 * Tells the borrower and each investor how a campaign's funding ended, once per investor with
 * their own figures, after the money has moved: paid out (PB-34) or not funded and refunded
 * (PB-20).
 */
class FundingNotifier
{
    /**
     * Funded and paid out: the borrower gets the amount and the first row of the schedule, each
     * investor their pledged total and their share of that first row.
     */
    public function disbursed(Campaign $campaign): void
    {
        $first = SchedulePresenter::first($campaign);
        $campaign->borrower->notify(new CampaignDisbursed($campaign, CampaignDisbursed::BORROWER, $first['dateLabel'], $first['cents']));

        $totals = Pledge::where('campaign_id', $campaign->id)->where('status', 'settled')
            ->selectRaw('investor_id, SUM(amount) as total')->groupBy('investor_id')->pluck('total', 'investor_id');
        foreach (User::whereKey($totals->keys())->get() as $investor) {
            $investor->notify(new CampaignDisbursed(
                $campaign,
                CampaignDisbursed::INVESTOR,
                $first['dateLabel'],
                $first['shares'][$investor->id],
                Money::fromDatabase($totals[$investor->id]),
            ));
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
