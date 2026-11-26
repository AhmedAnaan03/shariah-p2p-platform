<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * All-or-nothing settlement at the funding deadline (PB-20), run every minute by the scheduler
 * (`campaigns:settle-deadlines`).
 *
 * For each live campaign whose deadline has passed, in its own transaction that locks the
 * campaign row first (the same lock pledges take, so a pledge can't land mid-settlement):
 *
 * - target reached → funded (normally the last pledge already did this);
 * - otherwise → failed, and every held pledge goes back from escrow to its investor's wallet as
 *   a pledge_refund transfer, and is marked refunded.
 *
 * Running it again changes nothing: a settled campaign is no longer live, and a refunded pledge
 * is no longer held. Two copies running at once are safe for the same reason, under the lock.
 */
class SettlementService
{
    public const FUNDED = Campaign::STATUS_FUNDED;

    public const FAILED = Campaign::STATUS_FAILED;

    public function __construct(private readonly WalletLedger $ledger, private readonly FundingNotifier $notifier) {}

    /**
     * Settle every live campaign past its deadline.
     *
     * @return array{funded: int, failed: int, refunds: int}
     */
    public function settleDue(): array
    {
        $summary = ['funded' => 0, 'failed' => 0, 'refunds' => 0];
        $due = Campaign::where('status', Campaign::STATUS_LIVE)->where('funding_deadline', '<=', now())->orderBy('funding_deadline')->pluck('id');

        foreach ($due as $id) {
            try {
                $result = $this->settle($id);
            } catch (Throwable $e) {
                // One campaign failing must not stop the others; it is retried next minute.
                Log::error("Settling campaign {$id} failed: {$e->getMessage()}");

                continue;
            }
            if ($result !== null) {
                $summary[$result['outcome']]++;
                $summary['refunds'] += $result['pledgesRefunded'];
            }
        }

        return $summary;
    }

    /**
     * Settle one campaign if it is live and past its deadline; null if there was nothing to do.
     *
     * @return array{outcome: string, refunds: array<int, int>, pledgesRefunded: int}|null refunds: investor id => refunded cents
     */
    public function settle(int $campaignId): ?array
    {
        $result = WalletLedger::run(function () use ($campaignId) {
            $campaign = Campaign::whereKey($campaignId)->lockForUpdate()->first();
            if ($campaign === null || $campaign->status !== Campaign::STATUS_LIVE || $campaign->funding_deadline->isFuture()) {
                return null;
            }

            if (PledgeService::remainingCents($campaign) === 0) {
                self::markFunded($campaign);

                return ['campaign' => $campaign, 'outcome' => self::FUNDED, 'refunds' => [], 'pledgesRefunded' => 0];
            }

            $refunds = [];
            $pledgesRefunded = 0;
            $escrow = WalletAccount::escrowFor($campaign);
            // Locked too, so nothing else can change them while they are refunded.
            foreach (Pledge::where('campaign_id', $campaign->id)->where('status', 'held')->orderBy('id')->lockForUpdate()->get() as $pledge) {
                $cents = Money::fromDatabase($pledge->amount);
                $this->ledger->transfer($escrow, WalletAccount::forUser($pledge->investor), $cents, WalletTransaction::PLEDGE_REFUND, $pledge, "Refund: \"{$campaign->title}\" was not funded");
                $pledge->forceFill(['status' => 'refunded'])->save();
                $refunds[$pledge->investor_id] = ($refunds[$pledge->investor_id] ?? 0) + $cents;
                $pledgesRefunded++;
            }

            $campaign->forceFill(['status' => self::FAILED, 'status_reason' => 'The funding deadline passed before the target was reached.'])->save();
            AuditLog::record('campaign.not_funded', $campaign, ['refunded' => array_map(fn (int $c) => Money::fromCents($c), $refunds)]);

            return ['campaign' => $campaign, 'outcome' => self::FAILED, 'refunds' => $refunds, 'pledgesRefunded' => $pledgesRefunded];
        });

        if ($result === null) {
            return null;
        }

        // After commit: only tell people once the money has really moved.
        $result['outcome'] === self::FUNDED
            ? $this->notifier->funded($result['campaign'])
            : $this->notifier->notFunded($result['campaign'], $result['refunds']);

        return ['outcome' => $result['outcome'], 'refunds' => $result['refunds'], 'pledgesRefunded' => $result['pledgesRefunded']];
    }

    /**
     * Mark a (locked) campaign funded. The money stays in escrow, with its pledges held, until it
     * is paid out to the borrower (PB-34).
     */
    public static function markFunded(Campaign $campaign): void
    {
        $campaign->forceFill(['status' => self::FUNDED, 'funded_at' => now()])->save();
        AuditLog::record('campaign.funded', $campaign, ['target' => (string) $campaign->amount_requested]);
    }
}
