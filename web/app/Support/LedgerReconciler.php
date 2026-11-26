<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
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
 *  8. Repayments (PB-35, PB-36): each has exactly one `repayment` transfer of its amount from its
 *     campaign's borrower into the campaign's escrow; its capital is the row's capital_due and its
 *     return the row's return_due (a conventional early payoff may charge less, never more); its
 *     payouts add up to exactly its capital and its return, separately; a paid instalment has
 *     its repayment, and an unpaid one has none.
 *  9. Mudarabah reports (PB-29): an approved report's payouts add up to exactly its capital
 *     returned, restored losses, investors' profit and loss borne, and its money arrived in one
 *     `mudarabah_payment` from the business into the escrow; a report not approved moved nothing.
 *     Every payout transfer (`return_payout`, `mudarabah_payout`) goes from its campaign's escrow
 *     to its investor's wallet, for exactly the money its investor_payouts row records, and every
 *     such transfer has a row.
 * 10. Mudarabah capital accounts (PB-29): no investor's outstanding capital or unrestored loss is
 *     ever below zero, and a completed Mudarabah has no capital left outstanding.
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

        // 8 to 10. Repayments, Mudarabah reports and capital accounts.
        array_push($problems, ...self::repaymentProblems());
        array_push($problems, ...self::reportProblems());
        array_push($problems, ...self::payoutTransferProblems());
        array_push($problems, ...self::capitalAccountProblems());

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

    /**
     * Rule 8.
     *
     * @return list<string>
     */
    private static function repaymentProblems(): array
    {
        $problems = [];
        $repayments = DB::table('repayments as r')
            ->join('repayment_schedules as s', 's.id', '=', 'r.repayment_schedule_id')
            ->join('campaigns as c', 'c.id', '=', 's.campaign_id')
            ->get(['r.id', 'r.amount', 'r.capital_amount', 'r.return_amount', 'r.kind', 's.capital_due', 's.return_due', 's.campaign_id', 'c.borrower_id', 'c.contract_type']);
        $legs = self::legs(WalletTransaction::REPAYMENT)->groupBy('repayment_id');
        $payouts = DB::table('investor_payouts')->whereNotNull('repayment_id')
            ->selectRaw('repayment_id, SUM(capital_amount) as capital, SUM(return_amount) as ret')->groupBy('repayment_id')->get()->keyBy('repayment_id');

        foreach ($repayments as $r) {
            $amount = Money::fromDatabase($r->amount);
            $capital = Money::fromDatabase($r->capital_amount);
            $return = Money::fromDatabase($r->return_amount);
            $dueReturn = Money::fromDatabase($r->return_due);

            $transfers = ($legs[$r->id] ?? collect())->groupBy('transfer_ref');
            $ok = $transfers->count() === 1 && self::isTransfer($transfers->first(), WalletAccount::TYPE_USER, (int) $r->borrower_id, WalletAccount::TYPE_ESCROW, (int) $r->campaign_id, $amount);
            if (! $ok) {
                $problems[] = "Repayment {$r->id} is not one transfer of ".Money::fromCents($amount).' from its borrower into its campaign\'s escrow.';
            }
            if ($capital !== Money::fromDatabase($r->capital_due)) {
                $problems[] = "Repayment {$r->id}: capital {$r->capital_amount} but its instalment's capital is {$r->capital_due}.";
            }
            $earlyConventional = $r->kind === 'early_payoff' && $r->contract_type === Campaign::CONTRACT_CONVENTIONAL;
            if ($earlyConventional ? $return > $dueReturn : $return !== $dueReturn) {
                $problems[] = "Repayment {$r->id}: return {$r->return_amount} doesn't match its instalment's {$r->return_due}.";
            }
            $paid = $payouts[$r->id] ?? null;
            if (Money::fromDatabase($paid?->capital) !== $capital || Money::fromDatabase($paid?->ret) !== $return) {
                $problems[] = "Repayment {$r->id}: its payouts don't add up to its capital and return exactly.";
            }
        }

        $paidRows = DB::table('repayment_schedules as s')->join('campaigns as c', 'c.id', '=', 's.campaign_id')
            ->where('c.contract_type', '!=', Campaign::CONTRACT_MUDARABAH)
            ->leftJoin('repayments as r', 'r.repayment_schedule_id', '=', 's.id')
            ->get(['s.id', 's.status', 'r.id as repayment_id']);
        foreach ($paidRows as $row) {
            if (($row->status === 'paid') !== ($row->repayment_id !== null)) {
                $problems[] = "Instalment {$row->id} is {$row->status} but ".($row->repayment_id === null ? 'has no repayment.' : 'has a repayment.');
            }
        }

        return $problems;
    }

    /**
     * Rule 9, for the reports themselves.
     *
     * @return list<string>
     */
    private static function reportProblems(): array
    {
        $problems = [];
        $reports = DB::table('business_reports as b')->join('campaigns as c', 'c.id', '=', 'b.campaign_id')
            ->get(['b.id', 'b.status', 'b.capital_returned', 'b.loss_restored', 'b.investors_profit', 'b.loss_borne', 'b.campaign_id', 'c.borrower_id']);
        $legs = self::legs(WalletTransaction::MUDARABAH_PAYMENT)->groupBy('business_report_id');
        $payouts = DB::table('investor_payouts')->whereNotNull('business_report_id')
            ->selectRaw('business_report_id, COUNT(*) as n, SUM(capital_amount) as capital, SUM(loss_restored_amount) as restored, SUM(return_amount) as ret, SUM(loss_amount) as loss')
            ->groupBy('business_report_id')->get()->keyBy('business_report_id');

        foreach ($reports as $b) {
            $paid = $payouts[$b->id] ?? null;
            $transfers = ($legs[$b->id] ?? collect())->groupBy('transfer_ref');

            if ($b->status !== 'approved') {
                if ($paid !== null || $transfers->isNotEmpty()) {
                    $problems[] = "Business report {$b->id} is {$b->status} but money moved for it.";
                }

                continue;
            }

            $sums = [
                'capital returned' => [Money::fromDatabase($paid?->capital), Money::fromDatabase($b->capital_returned)],
                'losses restored' => [Money::fromDatabase($paid?->restored), Money::fromDatabase($b->loss_restored)],
                'investors\' profit' => [Money::fromDatabase($paid?->ret), Money::fromDatabase($b->investors_profit)],
                'loss borne' => [Money::fromDatabase($paid?->loss), Money::fromDatabase($b->loss_borne)],
            ];
            foreach ($sums as $what => [$actual, $expected]) {
                if ($actual !== $expected) {
                    $problems[] = "Business report {$b->id}: payouts give {$what} ".Money::fromCents($actual).', the report '.Money::fromCents($expected).'.';
                }
            }

            $money = $sums['capital returned'][1] + $sums['losses restored'][1] + $sums['investors\' profit'][1];
            $ok = $money === 0
                ? $transfers->isEmpty()
                : $transfers->count() === 1 && self::isTransfer($transfers->first(), WalletAccount::TYPE_USER, (int) $b->borrower_id, WalletAccount::TYPE_ESCROW, (int) $b->campaign_id, $money);
            if (! $ok) {
                $problems[] = "Business report {$b->id}: its ".Money::fromCents($money).' did not arrive in one payment from the business into the escrow.';
            }
        }

        return $problems;
    }

    /**
     * Rule 9, for every payout transfer.
     *
     * @return list<string>
     */
    private static function payoutTransferProblems(): array
    {
        $problems = [];
        $legs = self::legs(WalletTransaction::RETURN_PAYOUT)->merge(self::legs(WalletTransaction::MUDARABAH_PAYOUT))->groupBy('transfer_ref');
        $rows = DB::table('investor_payouts')->whereNotNull('transfer_ref')->get()->keyBy('transfer_ref');

        foreach ($rows as $ref => $row) {
            $money = Money::fromDatabase($row->capital_amount) + Money::fromDatabase($row->loss_restored_amount) + Money::fromDatabase($row->return_amount);
            $type = $row->repayment_id !== null ? WalletTransaction::RETURN_PAYOUT : WalletTransaction::MUDARABAH_PAYOUT;
            $transfer = $legs[$ref] ?? null;
            if ($transfer === null || $transfer->first()->type !== $type
                || ! self::isTransfer($transfer, WalletAccount::TYPE_ESCROW, (int) $row->campaign_id, WalletAccount::TYPE_USER, (int) $row->investor_id, $money)) {
                $problems[] = "Payout {$row->id} is not one {$type} of ".Money::fromCents($money).' from its campaign\'s escrow to its investor.';
            }
        }
        foreach ($legs->keys()->diff($rows->keys()) as $ref) {
            $problems[] = "Payout transfer {$ref} has no investor_payouts row.";
        }

        return $problems;
    }

    /**
     * Rule 10.
     *
     * @return list<string>
     */
    private static function capitalAccountProblems(): array
    {
        $problems = [];
        foreach (Campaign::where('contract_type', Campaign::CONTRACT_MUDARABAH)->whereNotNull('disbursed_at')->get() as $campaign) {
            $accounts = MudarabahCapital::accounts($campaign);
            foreach ($accounts as $investorId => $account) {
                if ($account['outstanding'] < 0) {
                    $problems[] = "Campaign {$campaign->id}: investor {$investorId}'s outstanding capital is below zero.";
                }
                if ($account['unrestored'] < 0) {
                    $problems[] = "Campaign {$campaign->id}: investor {$investorId}'s unrestored loss is below zero (more was restored than they lost).";
                }
            }
            if ($campaign->status === Campaign::STATUS_COMPLETED && MudarabahCapital::totals($accounts)['outstanding'] !== 0) {
                $problems[] = "Campaign {$campaign->id} is completed but still has capital outstanding.";
            }
        }

        return $problems;
    }

    /**
     * Every leg of one type, with its account's owner.
     */
    private static function legs(string $type): Collection
    {
        return DB::table('wallet_transactions as t')
            ->join('wallet_accounts as a', 'a.id', '=', 't.wallet_account_id')
            ->where('t.type', $type)
            ->get(['t.transfer_ref', 't.type', 't.direction', 't.amount', 't.repayment_id', 't.business_report_id', 'a.type as account_type', 'a.user_id', 'a.campaign_id']);
    }

    /**
     * Whether a transfer's two legs are a debit from one account and a credit to another, of
     * $cents. An account is named by its type and its owner (user id or campaign id).
     */
    private static function isTransfer(Collection $legs, string $fromType, int $fromOwner, string $toType, int $toOwner, int $cents): bool
    {
        $debit = $legs->firstWhere('direction', WalletTransaction::DEBIT);
        $credit = $legs->firstWhere('direction', WalletTransaction::CREDIT);
        $owner = fn ($leg) => (int) ($leg->account_type === WalletAccount::TYPE_ESCROW ? $leg->campaign_id : $leg->user_id);

        return $legs->count() === 2 && $debit && $credit
            && $debit->account_type === $fromType && $owner($debit) === $fromOwner
            && $credit->account_type === $toType && $owner($credit) === $toOwner
            && Money::fromDatabase($debit->amount) === $cents;
    }
}
