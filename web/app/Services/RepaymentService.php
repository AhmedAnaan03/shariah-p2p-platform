<?php

namespace App\Services;

use App\Exceptions\InsufficientFunds;
use App\Exceptions\RepaymentRefused;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\InvestorPayout;
use App\Models\Pledge;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Support\CampaignInvestors;
use App\Support\InstalmentSplit;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Repayments from the borrower's wallet (PB-35) and their pro-rata payout to the investors
 * (PB-36), for conventional and Murabahah campaigns. A Mudarabah is never repaid: its money comes
 * from approved business reports (BusinessReportService).
 *
 * Every payment is one database transaction (READ COMMITTED, WalletLedger::run) that locks the
 * campaign's row first, the same lock pledges and the payout take, then the schedule rows, then
 * (inside WalletLedger) the wallets in id order. For each instalment paid:
 *
 *  1. a `repayments` row: the amount, split into capital and return;
 *  2. one balanced `repayment` transfer from the borrower's wallet into the campaign's escrow,
 *     refused (InsufficientFunds) if the wallet holds too little;
 *  3. the escrow pays each investor their share (InstalmentSplit by settled pledges, capital and
 *     return split separately, so each adds up exactly), one `return_payout` transfer each, and
 *     an `investor_payouts` row records the two parts. The escrow is back at zero;
 *  4. the row becomes `paid`;
 *  5. after the last row, the campaign becomes `completed`, and the borrower and investors are
 *     told once the transaction has committed.
 *
 * All or nothing: if any step fails, nothing is paid.
 *
 * Double clicks: the request names the instalment it pays. A second click waits for the first
 * one's campaign lock, then finds that instalment paid and is refused with nothing charged. The
 * unique index on repayments.repayment_schedule_id refuses a second payment of a row even if this
 * check were bypassed.
 *
 * There are no late fees or penalty charges for any contract (decisions.md): a late instalment
 * costs exactly what it would have on time.
 */
class RepaymentService
{
    public function __construct(private readonly WalletLedger $ledger, private readonly RepaymentNotifier $notifier) {}

    /**
     * Pay the next unpaid instalment. $scheduleId is the instalment the borrower saw as next.
     *
     * @throws RepaymentRefused|InsufficientFunds
     */
    public function payNext(User $borrower, Campaign $campaign, int $scheduleId): Repayment
    {
        [$repayment, $completed] = WalletLedger::run(function () use ($borrower, $campaign, $scheduleId) {
            $campaign = $this->lockRepayable($borrower, $campaign);
            $unpaid = $this->lockUnpaid($campaign);
            $next = $unpaid->first();

            if ($next === null) {
                throw new RepaymentRefused('Every instalment is already paid.');
            }
            if ($next->id !== $scheduleId) {
                $asked = RepaymentSchedule::where('campaign_id', $campaign->id)->whereKey($scheduleId)->first();
                throw new RepaymentRefused($asked?->status === 'paid'
                    ? "Instalment {$asked->instalment_no} is already paid. Nothing more was taken from your wallet."
                    : "Instalments are paid in order: instalment {$next->instalment_no} is next.");
            }

            $repayment = $this->payRow($campaign, $next, Money::fromDatabase($next->capital_due), Money::fromDatabase($next->return_due), Repayment::KIND_INSTALMENT);

            return [$repayment, $this->completeIfFinished($campaign)];
        });

        if ($completed) {
            $this->notifier->completed($campaign->fresh());
        }

        return $repayment;
    }

    /**
     * Pay off every remaining instalment at once (PB-35). $quotedCents is the total the borrower
     * was shown; if it no longer matches (a due date passed in between), nothing is paid.
     *
     * @return int how many instalments were paid
     *
     * @throws RepaymentRefused|InsufficientFunds
     */
    public function payOff(User $borrower, Campaign $campaign, int $quotedCents): int
    {
        $count = WalletLedger::run(function () use ($borrower, $campaign, $quotedCents) {
            $campaign = $this->lockRepayable($borrower, $campaign);
            $unpaid = $this->lockUnpaid($campaign);
            if ($unpaid->isEmpty()) {
                throw new RepaymentRefused('This campaign is already paid off. Nothing more was taken from your wallet.');
            }

            $quote = self::quote($campaign, $unpaid);
            if ($quote['total'] !== $quotedCents) {
                throw new RepaymentRefused('The payoff amount is now '.Money::format($quote['total']).', not '.Money::format($quotedCents).'. Check it and confirm again.');
            }

            foreach ($quote['rows'] as $row) {
                $this->payRow($campaign, $row['schedule'], $row['capital'], $row['return'], Repayment::KIND_EARLY_PAYOFF);
            }
            AuditLog::record('repayment.paid_off', $campaign, [
                'instalments' => count($quote['rows']),
                'total' => Money::fromCents($quote['total']),
                'return_not_charged' => Money::fromCents($quote['waived']),
            ], $borrower);
            $this->completeIfFinished($campaign);

            return count($quote['rows']);
        });

        $this->notifier->completed($campaign->fresh());

        return $count;
    }

    /**
     * What paying off now costs, and how it is worked out (PB-35):
     *
     * - Conventional: the capital still owed, plus the interest of any instalment already due
     *   (today or earlier). Interest for periods not reached yet isn't charged.
     * - Murabahah: the remaining sale price in full, capital and markup. The markup was fixed
     *   when the contract was agreed (PB-28); a voluntary rebate (ibra') is a future enhancement.
     *
     * @param  Collection<int, RepaymentSchedule>|null  $unpaid
     * @return array{rows: list<array{schedule: RepaymentSchedule, capital: int, return: int, waived: int}>, capital: int, return: int, waived: int, total: int}
     */
    public static function quote(Campaign $campaign, ?Collection $unpaid = null): array
    {
        $unpaid ??= RepaymentSchedule::where('campaign_id', $campaign->id)->where('status', '!=', 'paid')->orderBy('instalment_no')->get();
        $today = Campaign::today();
        $conventional = $campaign->contract_type === Campaign::CONTRACT_CONVENTIONAL;

        $rows = [];
        $capital = $return = $waived = 0;
        foreach ($unpaid as $schedule) {
            $rowCapital = Money::fromDatabase($schedule->capital_due);
            $rowReturn = Money::fromDatabase($schedule->return_due);
            $charged = $conventional && $schedule->due_date->toDateString() > $today ? 0 : $rowReturn;
            $rows[] = ['schedule' => $schedule, 'capital' => $rowCapital, 'return' => $charged, 'waived' => $rowReturn - $charged];
            $capital += $rowCapital;
            $return += $charged;
            $waived += $rowReturn - $charged;
        }

        return ['rows' => $rows, 'capital' => $capital, 'return' => $return, 'waived' => $waived, 'total' => $capital + $return];
    }

    /**
     * Mark instalments late once their due date has passed, and back to due if a date moves
     * forward again (the demo tool). Conventional and Murabahah only: a Mudarabah's planned
     * capital return is never late. Run every minute by `repayments:mark-late`.
     *
     * @return int how many rows changed
     */
    public static function refreshLateStatus(?Campaign $campaign = null): int
    {
        $today = Campaign::today();
        $rows = fn () => RepaymentSchedule::query()
            ->when($campaign, fn ($q) => $q->where('campaign_id', $campaign->id))
            ->whereHas('campaign', fn ($q) => $q->where('status', Campaign::STATUS_REPAYING)->where('contract_type', '!=', Campaign::CONTRACT_MUDARABAH));

        $late = $rows()->where('status', 'due')->whereDate('due_date', '<', $today)->update(['status' => 'late', 'updated_at' => now()]);
        $due = $rows()->where('status', 'late')->whereDate('due_date', '>=', $today)->update(['status' => 'due', 'updated_at' => now()]);

        return $late + $due;
    }

    /**
     * The campaign, locked, if this borrower can repay it now.
     */
    private function lockRepayable(User $borrower, Campaign $campaign): Campaign
    {
        $campaign = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
        if ($campaign->borrower_id !== $borrower->id) {
            throw new RepaymentRefused('Only the borrower can repay this campaign.');
        }
        if ($campaign->isMudarabah()) {
            throw new RepaymentRefused('A Mudarabah has no instalments: capital is returned through business reports.');
        }
        if ($campaign->status === Campaign::STATUS_COMPLETED) {
            throw new RepaymentRefused('This campaign is already paid off. Nothing more was taken from your wallet.');
        }
        if ($campaign->status !== Campaign::STATUS_REPAYING) {
            throw new RepaymentRefused('This campaign has no instalments to pay yet.');
        }

        return $campaign;
    }

    /**
     * @return Collection<int, RepaymentSchedule>
     */
    private function lockUnpaid(Campaign $campaign): Collection
    {
        return RepaymentSchedule::where('campaign_id', $campaign->id)->where('status', '!=', 'paid')
            ->orderBy('instalment_no')->lockForUpdate()->get();
    }

    /**
     * Steps 1 to 4 for one instalment, inside the caller's transaction.
     */
    private function payRow(Campaign $campaign, RepaymentSchedule $schedule, int $capital, int $return, string $kind): Repayment
    {
        $total = $capital + $return;
        $repayment = new Repayment([
            'amount' => Money::fromCents($total),
            'capital_amount' => Money::fromCents($capital),
            'return_amount' => Money::fromCents($return),
            'kind' => $kind,
            'paid_at' => now(),
        ]);
        $repayment->repaymentSchedule()->associate($schedule);
        $repayment->save();

        $escrow = WalletAccount::escrowFor($campaign);
        $ref = $this->ledger->transfer(
            WalletAccount::forUser($campaign->borrower),
            $escrow,
            $total,
            WalletTransaction::REPAYMENT,
            description: "Instalment {$schedule->instalment_no} of \"{$campaign->title}\"",
            repayment: $repayment,
        );

        $this->payInvestors($campaign, $escrow, $repayment, $schedule, $capital, $return);

        $schedule->forceFill(['status' => 'paid', 'paid_at' => $repayment->paid_at])->save();
        AuditLog::record('repayment.paid', $campaign, [
            'instalment' => $schedule->instalment_no,
            'kind' => $kind,
            'amount' => Money::fromCents($total),
            'capital' => Money::fromCents($capital),
            'return' => Money::fromCents($return),
            'transfer_ref' => $ref,
        ]);

        return $repayment;
    }

    /**
     * PB-36: the escrow pays each investor their share, capital and return split separately by
     * settled pledges (largest remainder), so the shares add up to exactly what was repaid.
     */
    private function payInvestors(Campaign $campaign, WalletAccount $escrow, Repayment $repayment, RepaymentSchedule $schedule, int $capital, int $return): void
    {
        $weights = CampaignInvestors::weights($campaign);
        $pledges = Pledge::whereKey(CampaignInvestors::pledgeIds($campaign))->get()->keyBy('investor_id');
        $capitalShares = InstalmentSplit::shares($capital, $weights);
        $returnShares = InstalmentSplit::shares($return, $weights);

        foreach (array_keys($weights) as $investorId) {
            $cents = $capitalShares[$investorId] + $returnShares[$investorId];
            if ($cents === 0) {
                continue; // a tiny pledge's share of a few laari can round to nothing
            }
            $pledge = $pledges[$investorId];
            $ref = $this->ledger->transfer(
                $escrow,
                WalletAccount::forUser($pledge->investor),
                $cents,
                WalletTransaction::RETURN_PAYOUT,
                $pledge,
                "Your share of instalment {$schedule->instalment_no} of \"{$campaign->title}\"",
                repayment: $repayment,
            );
            InvestorPayout::create([
                'campaign_id' => $campaign->id,
                'investor_id' => $investorId,
                'pledge_id' => $pledge->id,
                'repayment_id' => $repayment->id,
                'capital_amount' => Money::fromCents($capitalShares[$investorId]),
                'return_amount' => Money::fromCents($returnShares[$investorId]),
                'transfer_ref' => $ref,
            ]);
        }
    }

    /**
     * After the last instalment: the campaign is completed. True if it just was.
     */
    private function completeIfFinished(Campaign $campaign): bool
    {
        if (RepaymentSchedule::where('campaign_id', $campaign->id)->where('status', '!=', 'paid')->exists()) {
            return false;
        }

        $campaign->forceFill(['status' => Campaign::STATUS_COMPLETED, 'completed_at' => now()])->save();
        AuditLog::record('campaign.completed', $campaign, ['contract' => $campaign->contract_type]);

        return true;
    }
}
