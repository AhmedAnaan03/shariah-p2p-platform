<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\InvestorPayout;

/**
 * Each investor's capital account in a Mudarabah (PB-29), derived from their settled pledges and
 * their investor_payouts rows, never stored separately, so it can't drift from the money:
 *
 *   outstanding capital = pledged − capital returned − genuine loss borne
 *   unrestored loss     = genuine loss borne − loss restored from later profit
 *
 * LedgerReconciler checks neither ever goes below zero.
 */
final class MudarabahCapital
{
    /**
     * @return array<int, array{pledged: int, capital: int, loss: int, restored: int, profit: int, outstanding: int, unrestored: int}>
     */
    public static function accounts(Campaign $campaign): array
    {
        $sums = InvestorPayout::where('campaign_id', $campaign->id)
            ->selectRaw('investor_id, SUM(capital_amount) as capital, SUM(loss_amount) as loss, SUM(loss_restored_amount) as restored, SUM(return_amount) as profit')
            ->groupBy('investor_id')->get()->keyBy('investor_id');

        $accounts = [];
        foreach (CampaignInvestors::weights($campaign) as $id => $pledged) {
            $row = $sums[$id] ?? null;
            $capital = Money::fromDatabase($row?->capital);
            $loss = Money::fromDatabase($row?->loss);
            $restored = Money::fromDatabase($row?->restored);
            $accounts[$id] = [
                'pledged' => $pledged,
                'capital' => $capital,
                'loss' => $loss,
                'restored' => $restored,
                'profit' => Money::fromDatabase($row?->profit),
                'outstanding' => $pledged - $capital - $loss,
                'unrestored' => $loss - $restored,
            ];
        }

        return $accounts;
    }

    /**
     * The whole campaign's figures: the sum of every investor's account.
     *
     * @param  array<int, array<string, int>>  $accounts
     * @return array{pledged: int, capital: int, loss: int, restored: int, profit: int, outstanding: int, unrestored: int}
     */
    public static function totals(array $accounts): array
    {
        $totals = ['pledged' => 0, 'capital' => 0, 'loss' => 0, 'restored' => 0, 'profit' => 0, 'outstanding' => 0, 'unrestored' => 0];
        foreach ($accounts as $account) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $account[$key];
            }
        }

        return $totals;
    }
}
