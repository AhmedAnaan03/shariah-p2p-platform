<?php

namespace App\Support;

use App\Models\Pledge;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Proves the simulated ledger is consistent (PB-18). Returns every problem found; an empty list
 * means it reconciles. Run by `php artisan wallet:reconcile` and at the end of the wallet,
 * pledging and settlement tests, including the concurrency tests.
 *
 *  1. Double entry: every transfer_ref has exactly two entries, one debit and one credit, of the
 *     same amount and type.
 *  2. Each account's balance_after values form an unbroken chain: every entry's balance_after is
 *     the previous one plus a credit or minus a debit, starting from zero.
 *  3. No user or escrow wallet is ever below zero, at any entry (only the external account is).
 *  4. All accounts together sum to zero: money is only ever moved, never created or lost.
 *  5. Each campaign's escrow holds exactly the sum of its held pledges.
 *  6. Every pledge's money moved exactly as its status says: a held pledge has one pledge transfer,
 *     a refunded pledge one pledge and one refund transfer.
 */
final class LedgerReconciler
{
    /**
     * @return list<string>
     */
    public static function problems(): array
    {
        $problems = [];

        // 1. Double entry.
        $broken = DB::table('wallet_transactions')
            ->select('transfer_ref')
            ->groupBy('transfer_ref')
            ->havingRaw("COUNT(*) <> 2 OR SUM(direction = 'debit') <> 1 OR MIN(amount) <> MAX(amount) OR COUNT(DISTINCT type) <> 1")
            ->pluck('transfer_ref');
        foreach ($broken as $ref) {
            $problems[] = "Transfer {$ref} is not one debit and one credit of the same amount.";
        }

        // 2 and 3. Each account's chain of balances.
        $types = WalletAccount::pluck('type', 'id');
        $running = [];
        WalletTransaction::orderBy('id')->select(['id', 'wallet_account_id', 'direction', 'amount', 'balance_after'])
            ->chunk(1000, function ($entries) use (&$running, &$problems, $types) {
                foreach ($entries as $entry) {
                    $account = $entry->wallet_account_id;
                    $cents = Money::fromDatabase($entry->amount);
                    $running[$account] = ($running[$account] ?? 0) + ($entry->direction === WalletTransaction::CREDIT ? $cents : -$cents);
                    $recorded = Money::fromDatabase($entry->balance_after);
                    if ($recorded !== $running[$account]) {
                        $problems[] = "Entry {$entry->id}: balance_after {$entry->balance_after} but the entries add up to ".Money::fromCents($running[$account]).'.';
                    }
                    if ($running[$account] < 0 && $types[$account] !== WalletAccount::TYPE_EXTERNAL) {
                        $problems[] = "Entry {$entry->id}: {$types[$account]} wallet {$account} went below zero.";
                    }
                }
            });
        $finals = $running;

        // 4. Money is conserved.
        $total = array_sum($finals);
        if ($total !== 0) {
            $problems[] = 'All accounts together should sum to zero but sum to '.Money::fromCents($total).'.';
        }

        // 5. Escrow equals the held pledges.
        foreach (WalletAccount::where('type', WalletAccount::TYPE_ESCROW)->get(['id', 'campaign_id']) as $escrow) {
            $held = Money::fromDatabase(Pledge::where('campaign_id', $escrow->campaign_id)->where('status', 'held')->sum('amount'));
            $balance = $finals[$escrow->id] ?? 0;
            if ($held !== $balance) {
                $problems[] = "Campaign {$escrow->campaign_id}: escrow holds ".Money::fromCents($balance).' but its held pledges total '.Money::fromCents($held).'.';
            }
        }

        // 6. Each pledge's transfers match its status.
        $counts = WalletTransaction::whereNotNull('pledge_id')->where('direction', WalletTransaction::CREDIT)
            ->selectRaw('pledge_id, type, COUNT(*) as n')->groupBy('pledge_id', 'type')->get()
            ->groupBy('pledge_id');
        foreach (Pledge::get(['id', 'status']) as $pledge) {
            $moves = ($counts[$pledge->id] ?? collect())->pluck('n', 'type');
            $expected = match ($pledge->status) {
                'refunded' => [WalletTransaction::PLEDGE => 1, WalletTransaction::PLEDGE_REFUND => 1],
                default => [WalletTransaction::PLEDGE => 1],
            };
            foreach ($expected as $type => $n) {
                if ((int) ($moves[$type] ?? 0) !== $n) {
                    $problems[] = "Pledge {$pledge->id} ({$pledge->status}) has ".(int) ($moves[$type] ?? 0)." {$type} transfer(s), expected {$n}.";
                }
            }
            if ($pledge->status !== 'refunded' && isset($moves[WalletTransaction::PLEDGE_REFUND])) {
                $problems[] = "Pledge {$pledge->id} ({$pledge->status}) was refunded but isn't marked refunded.";
            }
        }

        return $problems;
    }
}
