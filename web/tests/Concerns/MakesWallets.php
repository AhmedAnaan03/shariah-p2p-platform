<?php

namespace Tests\Concerns;

use App\Models\Campaign;
use App\Models\InvestorProfile;
use App\Models\Pledge;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\PledgeService;
use App\Services\WalletLedger;
use App\Support\LedgerReconciler;
use App\Support\Money;
use DateTimeInterface;

/**
 * Shared set-up for the wallet, pledging and settlement tests (PB-18 to PB-20).
 */
trait MakesWallets
{
    /** A verified investor who has accepted the risk acknowledgement, so the pledge gate allows them. */
    protected function investor(int $topUpCents = 0, bool $shariahOnly = false): User
    {
        $user = User::factory()->investor()->verified()->create();
        InvestorProfile::factory()->for($user)->create(['risk_acknowledged_at' => now(), 'shariah_only' => $shariahOnly]);
        if ($topUpCents > 0) {
            app(WalletLedger::class)->topUp($user, $topUpCents);
        }

        return $user;
    }

    /** A live campaign with a target in whole MVR. */
    protected function liveCampaign(int $targetMvr = 10_000, ?DateTimeInterface $deadline = null, string $contract = 'conventional'): Campaign
    {
        return Campaign::factory()->{$contract}()->live($deadline ?? now()->addDays(10))->create(['amount_requested' => $targetMvr]);
    }

    /**
     * A pledge held in escrow, made without PledgeService, so it doesn't fund (and pay out) the
     * campaign when it reaches the target: for the states just before funding or payout.
     */
    protected function heldPledge(User $investor, Campaign $campaign, int $cents): Pledge
    {
        $pledge = new Pledge(['amount' => Money::fromCents($cents)]);
        $pledge->forceFill(['campaign_id' => $campaign->id, 'investor_id' => $investor->id, 'status' => 'held'])->save();
        app(WalletLedger::class)->transfer(WalletAccount::forUser($investor), WalletAccount::escrowFor($campaign), $cents, WalletTransaction::PLEDGE, $pledge);

        return $pledge;
    }

    /**
     * A campaign funded by one investor per amount in $pledgesMvr (whole MVR) through the real
     * PledgeService, so the last pledge funds it and it is paid out (PB-34).
     *
     * @param  list<int>  $pledgesMvr
     * @param  array<string, mixed>  $attributes
     * @return array{0: Campaign, 1: list<User>}
     */
    protected function paidOutCampaign(string $contract = 'murabahah', int $targetMvr = 10_000, int $months = 3, array $pledgesMvr = [6_000, 4_000], array $attributes = []): array
    {
        $campaign = Campaign::factory()->{$contract}()->live(now()->addDays(10))
            ->create(['amount_requested' => $targetMvr, 'tenure_months' => $months, ...$attributes]);
        $investors = [];
        foreach ($pledgesMvr as $mvr) {
            $investor = $this->investor(topUpCents: $mvr * 100);
            app(PledgeService::class)->pledge($investor, $campaign, $mvr * 100);
            $investors[] = $investor;
        }

        return [$campaign->refresh(), $investors];
    }

    protected function balance(User $user): int
    {
        return app(WalletLedger::class)->balanceFor($user);
    }

    protected function assertLedgerReconciles(): void
    {
        $this->assertSame([], LedgerReconciler::problems(), 'The ledger must reconcile.');
    }
}
