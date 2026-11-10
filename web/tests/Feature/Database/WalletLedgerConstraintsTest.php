<?php

namespace Tests\Feature\Database;

use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\Repayment;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AssertsDatabaseConstraints;
use Tests\TestCase;

/**
 * Database-level guarantees for wallets, the ledger, pledges and repayments.
 */
class WalletLedgerConstraintsTest extends TestCase
{
    use AssertsDatabaseConstraints, RefreshDatabase;

    // --- wallet_accounts ---------------------------------------------------------------

    public function test_a_user_and_a_campaign_escrow_can_each_have_a_wallet(): void
    {
        $user = User::factory()->investor()->create();
        $campaign = Campaign::factory()->create();

        $user->wallet()->create(['type' => WalletAccount::TYPE_USER]);
        $campaign->escrowWallet()->create(['type' => WalletAccount::TYPE_ESCROW]);

        $this->assertDatabaseCount('wallet_accounts', 2);
        $this->assertSame($user->id, $user->wallet->user_id);
        $this->assertSame($campaign->id, $campaign->escrowWallet->campaign_id);
    }

    public function test_a_wallet_with_two_owners_is_rejected(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create();

        $this->assertCheckViolated('wallet_accounts_owner_check', fn () => WalletAccount::forceCreate([
            'type' => WalletAccount::TYPE_USER, 'user_id' => $user->id, 'campaign_id' => $campaign->id,
        ]));
    }

    public function test_a_wallet_with_no_owner_is_rejected(): void
    {
        $this->assertCheckViolated('wallet_accounts_owner_check', fn () => WalletAccount::forceCreate([
            'type' => WalletAccount::TYPE_USER,
        ]));
    }

    public function test_an_escrow_wallet_owned_by_a_user_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->assertCheckViolated('wallet_accounts_owner_check', fn () => WalletAccount::forceCreate([
            'type' => WalletAccount::TYPE_ESCROW, 'user_id' => $user->id,
        ]));
    }

    public function test_a_user_cannot_have_a_second_wallet(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['type' => WalletAccount::TYPE_USER]);

        $this->assertQueryRejected('Duplicate entry', fn () => $user->wallet()->create(['type' => WalletAccount::TYPE_USER]));
    }

    // --- wallet_transactions -----------------------------------------------------------

    public function test_a_top_up_needs_no_pledge_or_repayment(): void
    {
        $wallet = $this->userWallet();

        $wallet->transactions()->create($this->entry(['type' => 'top_up', 'amount' => 1000, 'balance_after' => 1000]));

        $this->assertDatabaseHas('wallet_transactions', ['type' => 'top_up', 'pledge_id' => null, 'repayment_id' => null]);
    }

    public function test_a_zero_amount_entry_is_rejected(): void
    {
        $wallet = $this->userWallet();

        $this->assertCheckViolated('wallet_transactions_amount_check', fn () => $wallet->transactions()->create(
            $this->entry(['amount' => 0, 'balance_after' => 0])
        ));
    }

    public function test_an_entry_leaving_a_negative_balance_is_rejected(): void
    {
        $wallet = $this->userWallet();

        $this->assertCheckViolated('wallet_transactions_balance_after_check', fn () => $wallet->transactions()->create(
            $this->entry(['type' => 'withdrawal', 'direction' => 'debit', 'amount' => 50, 'balance_after' => -0.01])
        ));
    }

    public function test_a_return_payout_with_only_a_pledge_is_rejected(): void
    {
        [$wallet, $pledge] = $this->payoutFixtures();

        $this->assertCheckViolated('wallet_transactions_return_payout_check', fn () => $wallet->transactions()->create(
            $this->entry(['type' => 'return_payout', 'pledge_id' => $pledge->id])
        ));
    }

    public function test_a_return_payout_with_only_a_repayment_is_rejected(): void
    {
        [$wallet, , $repayment] = $this->payoutFixtures();

        $this->assertCheckViolated('wallet_transactions_return_payout_check', fn () => $wallet->transactions()->create(
            $this->entry(['type' => 'return_payout', 'repayment_id' => $repayment->id])
        ));
    }

    public function test_a_return_payout_tracing_to_pledge_and_repayment_is_saved(): void
    {
        [$wallet, $pledge, $repayment] = $this->payoutFixtures();

        $payout = $wallet->transactions()->create($this->entry([
            'type' => 'return_payout', 'pledge_id' => $pledge->id, 'repayment_id' => $repayment->id,
        ]));

        $this->assertTrue($payout->pledge->is($pledge));
        $this->assertTrue($payout->repayment->is($repayment));
        $this->assertNotNull($payout->fresh()->created_at);
    }

    public function test_a_ledger_entry_pointing_at_a_missing_pledge_is_rejected(): void
    {
        $wallet = $this->userWallet();

        $this->assertQueryRejected('a foreign key constraint fails', fn () => $wallet->transactions()->create(
            $this->entry(['type' => 'pledge', 'direction' => 'debit', 'pledge_id' => 999_999])
        ));
    }

    // --- pledges and repayments ----------------------------------------------------------

    public function test_a_zero_pledge_is_rejected(): void
    {
        $campaign = Campaign::factory()->create();
        $investor = User::factory()->investor()->create();

        $this->assertCheckViolated('pledges_amount_check', fn () => Pledge::forceCreate([
            'campaign_id' => $campaign->id, 'investor_id' => $investor->id, 'amount' => 0,
        ]));
    }

    public function test_a_valid_pledge_is_held_by_default(): void
    {
        $campaign = Campaign::factory()->create();
        $investor = User::factory()->investor()->create();

        $pledge = Pledge::forceCreate(['campaign_id' => $campaign->id, 'investor_id' => $investor->id, 'amount' => 500]);

        $this->assertSame('held', $pledge->fresh()->status);
        $this->assertSame('500.00', $pledge->fresh()->amount);
    }

    public function test_a_negative_repayment_is_rejected(): void
    {
        $schedule = Campaign::factory()->create()->repaymentSchedules()->create([
            'instalment_no' => 1, 'due_date' => '2027-01-31', 'capital_due' => 4000, 'return_due' => 350,
        ]);

        $this->assertCheckViolated('repayments_amount_check', fn () => $schedule->repayments()->create([
            'amount' => -5, 'paid_at' => now(),
        ]));
    }

    // --- helpers ------------------------------------------------------------------------

    private function userWallet(): WalletAccount
    {
        return User::factory()->investor()->create()->wallet()->create(['type' => WalletAccount::TYPE_USER]);
    }

    /**
     * A valid ledger entry's attributes, with $overrides applied.
     */
    private function entry(array $overrides = []): array
    {
        return array_merge([
            'transfer_ref' => (string) Str::uuid(),
            'type' => 'top_up',
            'direction' => 'credit',
            'amount' => 100,
            'balance_after' => 100,
        ], $overrides);
    }

    /**
     * An investor's wallet, their pledge, and a repayment on the same campaign.
     *
     * @return array{WalletAccount, Pledge, Repayment}
     */
    private function payoutFixtures(): array
    {
        $campaign = Campaign::factory()->murabahah()->create();
        $investor = User::factory()->investor()->create();
        $wallet = $investor->wallet()->create(['type' => WalletAccount::TYPE_USER]);
        $pledge = Pledge::forceCreate(['campaign_id' => $campaign->id, 'investor_id' => $investor->id, 'amount' => 1000]);
        $schedule = $campaign->repaymentSchedules()->create([
            'instalment_no' => 1, 'due_date' => '2027-01-31', 'capital_due' => 4166.67, 'return_due' => 333.33,
        ]);
        $repayment = $schedule->repayments()->create(['amount' => 4500, 'paid_at' => now()]);

        return [$wallet, $pledge, $repayment];
    }
}
