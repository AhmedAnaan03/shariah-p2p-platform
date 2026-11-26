<?php

namespace Tests\Feature\Wallet;

use App\Models\BorrowerProfile;
use App\Models\User;
use App\Services\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-18: the wallet page, simulated top-up and withdrawal, validation and who may use them.
 */
class WalletPageTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    public function test_investors_and_borrowers_see_their_own_wallet(): void
    {
        $investor = $this->investor(topUpCents: 2_500_00);
        $borrower = BorrowerProfile::factory()->create()->user;
        app(WalletLedger::class)->topUp($borrower, 700_00);

        $this->actingAs($investor)->get('/investor/wallet')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Wallet/Show')
                ->where('available', '2500.00')->where('held', '0.00')->where('canMoveMoney', true)
                ->has('transactions.data', 1)->where('transactions.data.0.amount', '2500.00')
                ->where('urls.topUp', route('investor.wallet.top-up')));
        $this->actingAs($borrower)->get('/borrower/wallet')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('available', '700.00')->has('transactions.data', 1));
    }

    public function test_the_wallet_routes_are_for_investors_and_borrowers_only(): void
    {
        $this->get('/investor/wallet')->assertRedirect('/login');
        $this->post('/investor/wallet/top-up', ['amount' => 100])->assertRedirect('/login');
        $this->actingAs(User::factory()->admin()->create())->get('/investor/wallet')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/borrower/wallet')->assertForbidden();
        $this->actingAs($this->investor())->get('/borrower/wallet')->assertForbidden();
        $this->actingAs(User::factory()->borrower()->verified()->create())->post('/investor/wallet/top-up', ['amount' => 100])->assertForbidden();
    }

    public function test_an_unverified_account_sees_the_wallet_but_cannot_move_money(): void
    {
        $user = User::factory()->investor()->create(); // pending

        $this->actingAs($user)->get('/investor/wallet')
            ->assertInertia(fn (Assert $page) => $page->where('canMoveMoney', false)
                ->where('blockedReason', 'Your account must be verified before you can top up, withdraw or pledge.'));
        $this->actingAs($user)->post('/investor/wallet/top-up', ['amount' => 100])->assertForbidden();
        $this->actingAs($user)->post('/investor/wallet/withdraw', ['amount' => 1])->assertForbidden();
        $this->assertSame(0, $this->balance($user));
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function invalidTopUps(): array
    {
        return [
            'blank' => ['', 'Enter an amount.'],
            'letters' => ['abc', 'Enter an amount in MVR, like 5000 or 5000.50.'],
            'negative' => ['-50', 'Enter an amount in MVR, like 5000 or 5000.50.'],
            'three decimals' => ['10.555', 'Enter an amount in MVR, like 5000 or 5000.50.'],
            'zero' => ['0', 'Enter an amount in MVR, like 5000 or 5000.50.'],
            'below the minimum' => ['9.99', 'The smallest top-up is MVR 10.00.'],
            'above the maximum' => ['1000000.01', 'A top-up can be at most MVR 1,000,000.00.'],
        ];
    }

    #[DataProvider('invalidTopUps')]
    public function test_invalid_top_ups_are_refused(mixed $amount, string $message): void
    {
        $user = $this->investor();

        $this->actingAs($user)->from('/investor/wallet')->post('/investor/wallet/top-up', ['amount' => $amount])
            ->assertRedirect('/investor/wallet')->assertSessionHasErrors(['amount' => $message]);
        $this->assertSame(0, $this->balance($user));
    }

    public function test_valid_top_ups_at_both_limits(): void
    {
        $user = $this->investor();

        $this->actingAs($user)->post('/investor/wallet/top-up', ['amount' => '10'])->assertSessionHas('status', 'Added MVR 10.00 to your simulated wallet.');
        $this->actingAs($user)->post('/investor/wallet/top-up', ['amount' => 1_000_000])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/investor/wallet/top-up', ['amount' => 12.5])->assertSessionHasNoErrors();
        $this->assertSame(1_000_022_50, $this->balance($user));
        $this->assertLedgerReconciles();
    }

    /**
     * @return array<string, array{0: mixed, 1: ?string}>
     */
    public static function withdrawals(): array
    {
        return [
            'blank' => ['', 'Enter an amount.'],
            'letters' => ['abc', 'Enter an amount in MVR, like 5000 or 5000.50.'],
            'below the minimum' => ['0.99', 'The smallest withdrawal is MVR 1.00.'],
            'more than the balance' => ['500.01', 'You have MVR 500.00 available. Withdraw that or less.'],
            'exactly the balance' => ['500', null],
            'part of it' => ['120.50', null],
        ];
    }

    #[DataProvider('withdrawals')]
    public function test_withdrawals(mixed $amount, ?string $message): void
    {
        $user = $this->investor(topUpCents: 500_00);
        $response = $this->actingAs($user)->from('/investor/wallet')->post('/investor/wallet/withdraw', ['amount' => $amount]);

        if ($message === null) {
            $response->assertSessionHasNoErrors();
            $this->assertSame(500_00 - (int) round((float) $amount * 100), $this->balance($user));
        } else {
            $response->assertSessionHasErrors(['amount' => $message]);
            $this->assertSame(500_00, $this->balance($user));
        }
        $this->assertLedgerReconciles();
    }

    public function test_the_history_is_filtered_searched_and_paginated_and_shows_only_your_own(): void
    {
        $user = $this->investor();
        foreach (range(1, 17) as $i) {
            app(WalletLedger::class)->topUp($user, 10_00);
        }
        app(WalletLedger::class)->withdraw($user, 5_00);
        $this->investor(topUpCents: 99_00); // someone else's money

        $this->actingAs($user)->get('/investor/wallet')
            ->assertInertia(fn (Assert $page) => $page->where('transactions.total', 18)->has('transactions.data', 15)
                ->where('transactions.last_page', 2)->where('transactions.data.0.amount', '-5.00'));
        $this->actingAs($user)->get('/investor/wallet?type=withdrawal')
            ->assertInertia(fn (Assert $page) => $page->where('transactions.total', 1)->where('filters', ['type' => 'withdrawal']));
        $this->actingAs($user)->get('/investor/wallet?type=bogus&search=withdrawal')
            ->assertInertia(fn (Assert $page) => $page->where('transactions.total', 1)->where('filters', ['search' => 'withdrawal']));
    }
}
