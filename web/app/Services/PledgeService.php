<?php

namespace App\Services;

use App\Exceptions\InsufficientFunds;
use App\Exceptions\PledgeRefused;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;

/**
 * Pledging from the wallet into a campaign's escrow (PB-19), funding when the pledge reaches
 * the target (PB-20), and then paying it out to the borrower (PB-34, DisbursementService).
 *
 * Everything happens in one database transaction (READ COMMITTED, see WalletLedger::run) that
 * starts by locking the campaign's row.
 * Every pledge to a campaign takes that lock first, so pledges to one campaign run one after the
 * other: when two investors try to pledge the last MVR 5,000 at the same moment, the second waits,
 * then sees nothing left and is refused. The campaign can't be overfunded.
 *
 * Lock order is always campaign, then wallets (in id order, inside WalletLedger). The deadline
 * job uses the same order, so the two can't deadlock.
 */
class PledgeService
{
    /** The smallest pledge, unless less than this is left to raise. */
    public const MIN_CENTS = 100_00;

    public function __construct(private readonly WalletLedger $ledger, private readonly DisbursementService $disbursement) {}

    /**
     * @throws PledgeRefused|InsufficientFunds
     */
    public function pledge(User $investor, Campaign $campaign, int $cents): Pledge
    {
        [$pledge, $funded] = WalletLedger::run(function () use ($investor, $campaign, $cents) {
            $campaign = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            // Checked again under the lock: the campaign may have closed or filled up, or the
            // investor turned on Shariah-only, since the page was loaded.
            $access = Gate::forUser($investor)->inspect('pledge', $campaign);
            if ($access->denied()) {
                throw new PledgeRefused($access->message());
            }

            $remaining = self::remainingCents($campaign);
            if ($cents > $remaining) {
                throw new PledgeRefused('Only '.Money::format($remaining).' is left to raise. Pledge that or less.');
            }
            $minimum = min(self::MIN_CENTS, $remaining);
            if ($cents < $minimum) {
                throw new PledgeRefused('The smallest pledge is '.Money::format($minimum).'.');
            }

            $pledge = new Pledge(['amount' => Money::fromCents($cents)]);
            $pledge->forceFill(['campaign_id' => $campaign->id, 'investor_id' => $investor->id, 'status' => 'held'])->save();

            // Throws InsufficientFunds (and rolls everything back) if the wallet can't cover it.
            $this->ledger->transfer(
                WalletAccount::forUser($investor),
                WalletAccount::escrowFor($campaign),
                $cents,
                WalletTransaction::PLEDGE,
                $pledge,
                "Pledge to \"{$campaign->title}\"",
            );
            AuditLog::record('pledge.created', $pledge, ['campaign_id' => $campaign->id, 'amount' => Money::fromCents($cents)], $investor);

            // All or nothing: the pledge that reaches the target funds the campaign at once.
            $funded = $cents === $remaining;
            if ($funded) {
                SettlementService::markFunded($campaign);
            }

            return [$pledge, $funded ? $campaign : null];
        });

        // PB-34: the pledge that funds a campaign also pays it out, in its own transaction after
        // this one has committed, so a payout problem can never undo the pledge (the deadline
        // job retries it). The payout tells the borrower and the investors.
        if ($funded) {
            $this->disbursement->disburseAfterFunding($funded);
        }

        return $pledge;
    }

    /**
     * What is still to raise, in cents: the target minus pledges held or paid out. Under the
     * campaign's lock, at READ COMMITTED (WalletLedger::run), this sees every pledge committed
     * before the lock was granted.
     */
    public static function remainingCents(Campaign $campaign): int
    {
        $pledged = Money::fromDatabase(Pledge::where('campaign_id', $campaign->id)->whereIn('status', Pledge::COUNTED_STATUSES)->sum('amount'));

        return max(0, Money::fromDatabase($campaign->amount_requested) - $pledged);
    }
}
