<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\RepaymentSchedule;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Support\Money;
use App\Support\ScheduleGenerator;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Pays a funded campaign's escrow out to the borrower and creates its schedule (PB-34, PB-28).
 *
 * In one database transaction (READ COMMITTED, see WalletLedger::run) that locks the campaign
 * row first, the same lock pledges and the deadline job take:
 *
 *  1. the held pledges are locked and added up; they must equal the target (all or nothing);
 *  2. one `disbursement` transfer moves that total from the escrow to the borrower's wallet
 *     (one debit and one credit, so the escrow is back to zero);
 *  3. every held pledge becomes `settled`;
 *  4. the schedule is written (ScheduleGenerator), the first row one month after now;
 *  5. the campaign becomes `repaying`, with `disbursed_at` set.
 *
 * Either all of it happens or none of it. Running it again changes nothing, because the campaign
 * is no longer `funded`. The borrower and the investors are told after the commit.
 *
 * It runs straight after the pledge that funds a campaign (in its own transaction, so a problem
 * here can never undo an investor's pledge) and every minute in the deadline job, which pays out
 * anything still waiting in `funded`: a campaign funded at its deadline, or one whose payout
 * failed and is retried.
 */
class DisbursementService
{
    public function __construct(private readonly WalletLedger $ledger, private readonly FundingNotifier $notifier) {}

    /**
     * Pay out one campaign if it is funded; null if there was nothing to do.
     *
     * @return array{cents: int, instalments: int}|null
     */
    public function disburse(int $campaignId): ?array
    {
        $campaign = WalletLedger::run(function () use ($campaignId) {
            $campaign = Campaign::whereKey($campaignId)->lockForUpdate()->first();
            if ($campaign === null || $campaign->status !== Campaign::STATUS_FUNDED) {
                return null;
            }

            $pledges = Pledge::where('campaign_id', $campaign->id)->where('status', 'held')->orderBy('id')->lockForUpdate()->get();
            $cents = $pledges->sum(fn (Pledge $pledge) => Money::fromDatabase($pledge->amount));
            if ($cents !== Money::fromDatabase($campaign->amount_requested)) {
                throw new LogicException("Campaign {$campaign->id} is funded but its held pledges total ".Money::format($cents).', not its target.');
            }

            $ref = $this->ledger->transfer(
                WalletAccount::escrowFor($campaign),
                WalletAccount::forUser($campaign->borrower),
                $cents,
                WalletTransaction::DISBURSEMENT,
                description: "Disbursement: \"{$campaign->title}\" was funded",
            );
            Pledge::whereKey($pledges->modelKeys())->update(['status' => 'settled', 'updated_at' => now()]);

            $disbursedAt = now();
            foreach (ScheduleGenerator::forCampaign($campaign, $disbursedAt) as $row) {
                $schedule = new RepaymentSchedule([
                    'instalment_no' => $row['instalment_no'],
                    'due_date' => $row['due_date'],
                    'capital_due' => Money::fromCents($row['capital_cents']),
                    'return_due' => $row['return_cents'] === null ? null : Money::fromCents($row['return_cents']),
                ]);
                $schedule->campaign()->associate($campaign);
                $schedule->save(); // RepaymentSchedule checks return_due against the contract
            }

            $campaign->forceFill(['status' => Campaign::STATUS_REPAYING, 'disbursed_at' => $disbursedAt])->save();
            AuditLog::record('campaign.disbursed', $campaign, [
                'amount' => Money::fromCents($cents),
                'transfer_ref' => $ref,
                'pledges_settled' => $pledges->count(),
                'schedule_rows' => (int) $campaign->tenure_months,
            ]);

            return $campaign;
        });

        if ($campaign === null) {
            return null;
        }

        // After commit: only tell people once the money has really moved.
        $this->notifier->disbursed($campaign);

        return ['cents' => Money::fromDatabase($campaign->amount_requested), 'instalments' => (int) $campaign->tenure_months];
    }

    /**
     * Pay out every campaign waiting in `funded` (the deadline job). One failing doesn't stop
     * the others; it is logged and retried next minute.
     */
    public function disburseWaiting(): int
    {
        $paidOut = 0;
        foreach (Campaign::where('status', Campaign::STATUS_FUNDED)->orderBy('funded_at')->pluck('id') as $id) {
            try {
                $paidOut += $this->disburse($id) === null ? 0 : 1;
            } catch (Throwable $e) {
                Log::error("Paying out campaign {$id} failed: {$e->getMessage()}");
            }
        }

        return $paidOut;
    }

    /**
     * Straight after a pledge funds a campaign. A failure is logged, not thrown: the pledge has
     * already been committed, and the deadline job pays the campaign out within a minute.
     */
    public function disburseAfterFunding(Campaign $campaign): void
    {
        try {
            $this->disburse($campaign->id);
        } catch (Throwable $e) {
            Log::error("Paying out campaign {$campaign->id} after funding failed; the deadline job will retry: {$e->getMessage()}");
        }
    }
}
