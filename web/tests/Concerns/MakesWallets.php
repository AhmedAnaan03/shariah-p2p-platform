<?php

namespace Tests\Concerns;

use App\Models\Campaign;
use App\Models\InvestorProfile;
use App\Models\User;
use App\Services\WalletLedger;
use App\Support\LedgerReconciler;
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

    protected function balance(User $user): int
    {
        return app(WalletLedger::class)->balanceFor($user);
    }

    protected function assertLedgerReconciles(): void
    {
        $this->assertSame([], LedgerReconciler::problems(), 'The ledger must reconcile.');
    }
}
