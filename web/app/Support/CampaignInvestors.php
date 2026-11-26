<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Pledge;

/**
 * Who funded a paid-out campaign, and with how much: what every share of a repayment (PB-36) or
 * of a Mudarabah report (PB-29) is in proportion to. One place, so the schedule pages show
 * exactly the split the payouts use.
 */
final class CampaignInvestors
{
    /**
     * Each investor's settled pledges in cents, by investor id (ascending).
     *
     * @return array<int, int>
     */
    public static function weights(Campaign $campaign): array
    {
        return Pledge::where('campaign_id', $campaign->id)->where('status', 'settled')
            ->selectRaw('investor_id, SUM(amount) as total')->groupBy('investor_id')->orderBy('investor_id')
            ->pluck('total', 'investor_id')
            ->map(fn ($total) => Money::fromDatabase($total))
            ->all();
    }

    /**
     * Each investor's first settled pledge id: the pledge a payout is recorded against (an
     * investor who pledged twice gets one payout, split by their total).
     *
     * @return array<int, int>
     */
    public static function pledgeIds(Campaign $campaign): array
    {
        return Pledge::where('campaign_id', $campaign->id)->where('status', 'settled')
            ->selectRaw('investor_id, MIN(id) as first_id')->groupBy('investor_id')
            ->pluck('first_id', 'investor_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
