<?php

namespace Tests\Feature\Wallet;

use App\Exceptions\InsufficientFunds;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\WalletLedger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-18: every money movement is a pair of ledger entries in one transaction; balances are
 * derived from the ledger, never go negative, and always reconcile.
 */
class LedgerTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    private WalletLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(WalletLedger::class);
    }

    public function test_a_top_up_is_a_debit_of_the_outside_world_and_a_credit_of_the_wallet(): void
    {
        $user = $this->investor();
        $ref = $this->ledger->topUp($user, 5_000_00);

        $entries = WalletTransaction::where('transfer_ref', $ref)->orderBy('id')->get();
        $this->assertCount(2, $entries);
        $this->assertSame(['external', 'debit', '5000.00', '-5000.00'], [$entries[0]->wallet_type, $entries[0]->direction, (string) $entries[0]->amount, (string) $entries[0]->balance_after]);
        $this->assertSame(['user', 'credit', '5000.00', '5000.00'], [$entries[1]->wallet_type, $entries[1]->direction, (string) $entries[1]->amount, (string) $entries[1]->balance_after]);
        $this->assertSame(5_000_00, $this->balance($user));
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet.top_up', 'actor_id' => $user->id]);
        $this->assertLedgerReconciles();
    }

    public function test_the_balance_is_derived_from_the_ledger(): void
    {
        $user = $this->investor();
        $this->assertSame(0, $this->balance($user), 'No wallet yet: zero, and none is created just by looking.');
        $this->assertSame(0, WalletAccount::where('user_id', $user->id)->count());

        $this->ledger->topUp($user, 1_000_00);
        $this->ledger->topUp($user, 250_50);
        $this->ledger->withdraw($user, 300_00);

        $this->assertSame(950_50, $this->balance($user));
        $wallet = WalletAccount::forUser($user);
        $sum = WalletTransaction::where('wallet_account_id', $wallet->id)
            ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) as s")->value('s');
        $this->assertSame('950.50', (string) $sum, 'The latest balance_after equals the sum of the entries.');
        $this->assertLedgerReconciles();
    }

    public function test_a_withdrawal_larger_than_the_balance_is_refused_and_writes_nothing(): void
    {
        $user = $this->investor(topUpCents: 100_00);
        $before = WalletTransaction::count();

        try {
            $this->ledger->withdraw($user, 100_01);
            $this->fail('Expected InsufficientFunds.');
        } catch (InsufficientFunds) {
        }

        $this->assertSame($before, WalletTransaction::count(), 'Nothing written: the whole transaction rolled back.');
        $this->assertSame(100_00, $this->balance($user));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'wallet.withdrawal']);
        $this->assertLedgerReconciles();
    }

    public function test_the_whole_balance_can_be_withdrawn_to_exactly_zero(): void
    {
        $user = $this->investor(topUpCents: 100_00);
        $this->ledger->withdraw($user, 100_00);

        $this->assertSame(0, $this->balance($user));
        $this->assertLedgerReconciles();
    }

    public function test_zero_negative_and_same_account_transfers_are_refused(): void
    {
        $user = $this->investor(topUpCents: 100_00);
        $wallet = WalletAccount::forUser($user);

        foreach ([0, -100] as $cents) {
            try {
                $this->ledger->transfer(WalletAccount::external(), $wallet, $cents, WalletTransaction::TOP_UP);
                $this->fail("A transfer of {$cents} should be refused.");
            } catch (InvalidArgumentException) {
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $this->ledger->transfer($wallet, $wallet, 100, WalletTransaction::TOP_UP);
    }

    public function test_a_top_up_above_the_limit_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ledger->topUp($this->investor(), WalletLedger::MAX_TOP_UP_CENTS + 1);
    }

    public function test_the_database_itself_refuses_a_negative_user_balance(): void
    {
        $user = $this->investor(topUpCents: 100_00);
        $wallet = WalletAccount::forUser($user);

        // Bypassing the ledger code entirely: the CHECK constraint is the second line of defence.
        $this->expectException(QueryException::class);
        DB::table('wallet_transactions')->insert([
            'wallet_account_id' => $wallet->id, 'wallet_type' => 'user', 'transfer_ref' => 'tampered', 'type' => 'withdrawal',
            'direction' => 'debit', 'amount' => 200, 'balance_after' => -100,
        ]);
    }

    public function test_an_entry_must_carry_its_accounts_real_type(): void
    {
        $user = $this->investor(topUpCents: 100_00);
        $wallet = WalletAccount::forUser($user);

        // Claiming to be the external account to dodge the CHECK fails the composite foreign key.
        $this->expectException(QueryException::class);
        DB::table('wallet_transactions')->insert([
            'wallet_account_id' => $wallet->id, 'wallet_type' => 'external', 'transfer_ref' => 'tampered', 'type' => 'withdrawal',
            'direction' => 'debit', 'amount' => 200, 'balance_after' => -100,
        ]);
    }

    public function test_there_is_only_one_external_account(): void
    {
        $this->assertTrue(WalletAccount::external()->is(WalletAccount::external()));
        $this->expectException(QueryException::class);
        WalletAccount::create(['type' => 'external', 'singleton' => 1]);
    }

    public function test_the_reconciliation_command_passes_and_catches_tampering(): void
    {
        $user = $this->investor(topUpCents: 500_00);
        $this->artisan('wallet:reconcile')->assertSuccessful();

        // A lone entry with no partner, and a balance_after that breaks the chain.
        $wallet = WalletAccount::forUser($user);
        DB::table('wallet_transactions')->insert([
            'wallet_account_id' => $wallet->id, 'wallet_type' => 'user', 'transfer_ref' => 'lonely', 'type' => 'top_up',
            'direction' => 'credit', 'amount' => 10, 'balance_after' => 999,
        ]);

        $this->artisan('wallet:reconcile')
            ->expectsOutputToContain('Transfer lonely is not one debit and one credit')
            ->expectsOutputToContain('should sum to zero')
            ->assertFailed();
    }

    public function test_wallets_belong_to_one_user_each(): void
    {
        $user = User::factory()->investor()->create();
        $this->assertTrue(WalletAccount::forUser($user)->is(WalletAccount::forUser($user)));
        $this->assertSame(1, WalletAccount::where('user_id', $user->id)->count());
    }
}
