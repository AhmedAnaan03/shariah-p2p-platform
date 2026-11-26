<?php

namespace App\Support;

use App\Models\Campaign;
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
 *  7. Every paid-out campaign (PB-34) has exactly one disbursement transfer, from its own escrow
 *     to its own borrower's wallet, of exactly its settled pledges; settled pledges never exist
 *     without one. With rule 5 this proves a paid-out escrow is back to zero.
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

        // 7. Disbursements.
        array_push($problems, ...self::disbursementProblems());

        return $problems;
    }

    /**
     * @return list<string>
     */
    private static function disbursementProblems(): array
    {
        $problems = [];
        $legs = DB::table('wallet_transactions as t')
            ->join('wallet_accounts as a', 'a.id', '=', 't.wallet_account_id')
            ->where('t.type', WalletTransaction::DISBURSEMENT)
            ->get(['t.transfer_ref', 't.direction', 't.amount', 'a.type as account_type', 'a.campaign_id', 'a.user_id']);
        $debits = $legs->where('direction', WalletTransaction::DEBIT);
        $credits = $legs->where('direction', WalletTransaction::CREDIT)->keyBy('transfer_ref');

        $settled = Pledge::where('status', 'settled')->selectRaw('campaign_id, SUM(amount) as total')->groupBy('campaign_id')
            ->pluck('total', 'campaign_id')->map(fn ($total) => Money::fromDatabase($total));
        $borrowers = Campaign::whereIn('id', $settled->keys()->merge($debits->pluck('campaign_id'))->filter()->unique())->pluck('borrower_id', 'id');

        foreach ($debits as $debit) {
            if ($debit->account_type !== WalletAccount::TYPE_ESCROW) {
                $problems[] = "Disbursement {$debit->transfer_ref} is not paid from a campaign's escrow.";

                continue;
            }
            $to = $credits[$debit->transfer_ref] ?? null;
            if ($to === null || $to->account_type !== WalletAccount::TYPE_USER || (int) $to->user_id !== (int) ($borrowers[$debit->campaign_id] ?? 0)) {
                $problems[] = "Disbursement {$debit->transfer_ref} for campaign {$debit->campaign_id} is not paid to that campaign's borrower.";
            }
        }

        $byCampaign = $debits->where('account_type', WalletAccount::TYPE_ESCROW)->groupBy('campaign_id');
        foreach ($byCampaign as $campaignId => $paid) {
            if ($paid->count() !== 1) {
                $problems[] = "Campaign {$campaignId} was paid out {$paid->count()} times.";
            }
            $paidCents = $paid->sum(fn ($leg) => Money::fromDatabase($leg->amount));
            $settledCents = $settled[$campaignId] ?? 0;
            if ($paidCents !== $settledCents) {
                $problems[] = "Campaign {$campaignId}: paid out ".Money::fromCents($paidCents).' but its settled pledges total '.Money::fromCents($settledCents).'.';
            }
        }
        foreach ($settled->keys()->diff($byCampaign->keys()) as $campaignId) {
            $problems[] = "Campaign {$campaignId} has settled pledges but was never paid out.";
        }

        return $problems;
    }
}
