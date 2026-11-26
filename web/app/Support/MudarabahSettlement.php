<?php

namespace App\Support;

use App\Exceptions\ReportRefused;

/**
 * What one approved Mudarabah business report does to each investor's capital account (PB-29).
 * Pure arithmetic in whole cents (laari), with no database, so every rule is unit-tested on its
 * own. BusinessReportService applies the result; the admin review page shows it as a preview.
 *
 * Each investor's account going in: `outstanding` (capital still invested in the business) and
 * `unrestored` (genuine losses they bore in earlier periods, not yet restored).
 *
 * In this order:
 *
 *  1. Capital returned: split by outstanding capital (largest remainder, InstalmentSplit). It
 *     can't be more than the capital invested.
 *  2. A profit first restores earlier genuine losses (the classical rule: there is no profit
 *     while the capital is impaired, so profit makes the capital whole first). Restoration is
 *     the smaller of the profit and the total unrestored loss, split by each investor's
 *     unrestored loss, so nobody is restored more than they lost.
 *  3. What profit is left is shared at the agreed ratio: the investors' share is rounded down to
 *     the laari (the business keeps the laari left over), and it is split by outstanding capital
 *     at the start of the period.
 *  4. A loss is either
 *     - genuine (`business`): borne by the investors in proportion to their remaining capital,
 *       reducing it, at most down to zero; any loss beyond the capital is the business's own.
 *     - caused by negligence or misconduct (`negligence`): the business is liable; nothing is
 *       allocated to the investors, so their capital stays to be restored in full.
 *  5. The final period settles the capital: what is returned plus any genuine loss allocated
 *     must leave no capital outstanding, or the report can't be approved.
 *
 * InstalmentSplit's largest remainder never gives anyone more than their own weight when the
 * amount is at most the total weight, so no account can go below zero.
 */
final class MudarabahSettlement
{
    /**
     * @param  array<int, array{outstanding: int, unrestored: int}>  $accounts  investor id => account, in cents
     * @param  int  $profit  revenue − costs, in cents; negative for a loss
     * @param  int  $capitalReturned  capital the business returns this period, in cents
     * @param  int  $investorShareBp  the investors' profit share in basis points (60% = 6000)
     * @param  string|null  $lossCause  'business' or 'negligence'; required for a loss
     * @param  bool  $final  the campaign's last planned period
     * @return array{
     *     capital: int, restored: int, investorsProfit: int, businessProfit: int,
     *     lossBorne: int, businessLiable: int, payment: int, outstandingBefore: int, outstandingAfter: int,
     *     investors: array<int, array{capital: int, restored: int, profit: int, loss: int}>
     * }
     *
     * @throws ReportRefused
     */
    public static function calculate(array $accounts, int $profit, int $capitalReturned, int $investorShareBp, ?string $lossCause, bool $final): array
    {
        $outstanding = array_map(fn (array $account) => $account['outstanding'], $accounts);
        $unrestored = array_map(fn (array $account) => $account['unrestored'], $accounts);
        $totalOutstanding = array_sum($outstanding);

        if ($capitalReturned < 0 || $capitalReturned > $totalOutstanding) {
            throw new ReportRefused('The capital returned can be at most the capital still invested, '.Money::format($totalOutstanding).'.');
        }
        if ($investorShareBp <= 0 || $investorShareBp >= 10_000) {
            throw new ReportRefused('The profit-sharing ratio must give both sides a share.');
        }

        $investors = array_map(fn () => ['capital' => 0, 'restored' => 0, 'profit' => 0, 'loss' => 0], $accounts);
        $restored = $investorsProfit = $businessProfit = $lossBorne = $businessLiable = 0;

        // 1. Capital returned, by outstanding capital.
        foreach (self::split($capitalReturned, $outstanding) as $id => $cents) {
            $investors[$id]['capital'] = $cents;
        }

        if ($profit > 0) {
            // 2. Restore earlier genuine losses first.
            $restored = min($profit, array_sum($unrestored));
            foreach (self::split($restored, $unrestored) as $id => $cents) {
                $investors[$id]['restored'] = $cents;
            }

            // 3. Share what is left at the agreed ratio.
            $left = $profit - $restored;
            $investorsProfit = intdiv($left * $investorShareBp, 10_000);
            $businessProfit = $left - $investorsProfit;
            foreach (self::split($investorsProfit, $outstanding) as $id => $cents) {
                $investors[$id]['profit'] = $cents;
            }
        } elseif ($profit < 0) {
            // 4. A loss: genuine, or the business is liable.
            $loss = -$profit;
            if ($lossCause === null) {
                throw new ReportRefused('Choose whether the loss was a genuine business loss or caused by negligence or misconduct.');
            }
            if ($lossCause === 'business') {
                $remaining = [];
                foreach ($outstanding as $id => $cents) {
                    $remaining[$id] = $cents - $investors[$id]['capital'];
                }
                $lossBorne = min($loss, array_sum($remaining));
                foreach (self::split($lossBorne, $remaining) as $id => $cents) {
                    $investors[$id]['loss'] = $cents;
                }
            } else {
                $businessLiable = $loss;
            }
        }

        $outstandingAfter = $totalOutstanding - $capitalReturned - $lossBorne;

        // 5. The last period settles the capital.
        if ($final && $outstandingAfter > 0) {
            throw new ReportRefused('This is the final period, so it must settle the capital: '
                .Money::format($outstandingAfter).' would still be invested. The business returns all remaining capital, less any genuine loss.');
        }

        return [
            'capital' => $capitalReturned,
            'restored' => $restored,
            'investorsProfit' => $investorsProfit,
            'businessProfit' => $businessProfit,
            'lossBorne' => $lossBorne,
            'businessLiable' => $businessLiable,
            // What moves from the business's wallet to the investors.
            'payment' => $capitalReturned + $restored + $investorsProfit,
            'outstandingBefore' => $totalOutstanding,
            'outstandingAfter' => $outstandingAfter,
            'investors' => $investors,
        ];
    }

    /**
     * InstalmentSplit over the investors with something to split by; 0 splits to nobody.
     *
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    private static function split(int $cents, array $weights): array
    {
        $weights = array_filter($weights, fn (int $weight) => $weight > 0);
        if ($cents === 0 || $weights === []) {
            return [];
        }

        return InstalmentSplit::shares($cents, $weights);
    }
}
