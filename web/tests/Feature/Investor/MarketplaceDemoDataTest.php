<?php

namespace Tests\Feature\Investor;

use App\Models\Campaign;
use App\Models\User;
use App\Services\PledgeService;
use App\Services\WalletLedger;
use App\Support\LedgerReconciler;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The demo accounts can show PB-16, PB-17 and PB-31 in the browser: more than a page of open
 * campaigns, a Shariah-only investor, and a campaign with a repayment history.
 */
class MarketplaceDemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_data_fills_the_marketplace(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(14, Campaign::listed()->count());

        $this->actingAs(User::where('email', 'investor@example.com')->first())->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.total', 14)->where('campaigns.last_page', 2)->where('shariahOnly', false));

        $this->actingAs(User::where('email', 'shariah-investor@example.com')->first())->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.total', 9)->where('shariahOnly', true));

        $bakery = Campaign::listed()->where('title', 'Bakery deck oven and mixer')->firstOrFail();
        $this->actingAs(User::where('email', 'investor@example.com')->first())->get("/investor/campaigns/{$bakery->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('repaymentHistory.instalmentsDue', 8)
                ->where('repaymentHistory.paidOnTime', 7)
                ->where('repaymentHistory.paidLate', 1));
    }

    public function test_the_demo_wallets_and_the_two_watchable_deadlines(): void
    {
        $this->seed(DatabaseSeeder::class);

        // PB-18: seeded through the ledger, so it reconciles.
        $this->assertSame([], LedgerReconciler::problems());
        $investor = User::where('email', 'investor@example.com')->first();
        $this->assertSame(34_000_00, app(WalletLedger::class)->balanceFor($investor), 'MVR 50,000 less the MVR 1,000 pledge and MVR 15,000 in paid-out campaigns.');
        $this->assertSame(35_000_00, app(WalletLedger::class)->balanceFor(User::where('email', 'shariah-investor@example.com')->first()));

        // PB-20: one campaign to fund by pledging the last MVR 4,000, one that will miss its target.
        $nearly = Campaign::where('title', 'Solar water heaters for the guest house')->sole();
        $short = Campaign::where('title', 'Coconut oil press')->sole();
        $this->assertSame(4_000_00, PledgeService::remainingCents($nearly));
        $this->assertTrue($nearly->funding_deadline->between(now()->addMinutes(9), now()->addMinutes(11)));
        $this->assertTrue($short->funding_deadline->between(now()->addMinutes(4), now()->addMinutes(6)));
        $this->assertSame(50_000_00, PledgeService::remainingCents($short));
        $sme = User::where('email', 'sme@example.com')->first();
        $this->assertTrue($nearly->borrower->is($sme), 'A demo borrower login owns the nearly funded campaign.');
        $this->actingAs($sme)->get("/borrower/campaigns/{$nearly->id}")
            ->assertInertia(fn (Assert $page) => $page->where('funding.raised', 36_000)->where('funding.investors', 3)->where('funding.remaining', '4000.00'));

        // After its deadline the job refunds it, including the demo investor's pledge.
        $this->travel(6)->minutes();
        $this->artisan('campaigns:settle-deadlines')->assertSuccessful();
        $this->assertSame(Campaign::STATUS_FAILED, $short->fresh()->status);
        $this->assertSame(35_000_00, app(WalletLedger::class)->balanceFor($investor), 'The MVR 1,000 came back.');
        $this->assertSame(Campaign::STATUS_LIVE, $nearly->fresh()->status, 'Still open for another few minutes.');
        $this->assertSame([], LedgerReconciler::problems());
    }

    public function test_two_demo_campaigns_are_already_paid_out_with_schedules(): void
    {
        $this->seed(DatabaseSeeder::class);

        $laundry = Campaign::where('title', 'Laundry machines for the guest house')->sole();
        $truck = Campaign::where('title', 'Food truck for Malé evenings')->sole();
        $this->assertSame([Campaign::STATUS_REPAYING, 12], [$laundry->status, $laundry->repaymentSchedules()->count()]);
        $this->assertSame([Campaign::STATUS_REPAYING, 6], [$truck->status, $truck->repaymentSchedules()->count()]);
        $this->assertSame(25_000_00, app(WalletLedger::class)->balanceFor(User::where('email', 'sme@example.com')->first()), 'MVR 5,000 top-up + MVR 20,000 paid out.');
        $this->assertSame(30_000_00, app(WalletLedger::class)->balanceFor(User::where('email', 'borrower@example.com')->first()));

        $this->actingAs(User::where('email', 'investor@example.com')->first())->get("/investor/campaigns/{$truck->id}")
            ->assertInertia(fn (Assert $page) => $page->where('schedule.labels.row', 'Planned capital return')->where('schedule.yourPledged', '10000.00'));
        $this->actingAs(User::where('email', 'sme@example.com')->first())->get("/borrower/campaigns/{$laundry->id}")
            ->assertInertia(fn (Assert $page) => $page->where('schedule.total', '21600.00'));
        $this->assertSame([], LedgerReconciler::problems());
    }
}
