<?php

namespace Tests\Feature\Wallet;

use App\Models\Campaign;
use App\Models\InvestorProfile;
use App\Models\Pledge;
use App\Models\User;
use App\Models\WalletAccount;
use App\Notifications\FundingOutcome;
use App\Services\PledgeService;
use App\Services\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-19: pledge from the wallet into the campaign's escrow, within the wallet balance and the
 * remaining amount, through the pledge gate. PB-20: progress updates and funding on target.
 */
class PledgeTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 10:00:00');
        Notification::fake();
    }

    private function pledge(User $investor, Campaign $campaign, mixed $amount)
    {
        return $this->actingAs($investor)->from("/investor/campaigns/{$campaign->id}")
            ->post("/investor/campaigns/{$campaign->id}/pledges", ['amount' => $amount]);
    }

    public function test_a_pledge_moves_money_from_the_wallet_into_escrow_and_is_held(): void
    {
        $investor = $this->investor(topUpCents: 5_000_00);
        $campaign = $this->liveCampaign(targetMvr: 10_000);

        $this->pledge($investor, $campaign, 2_000)->assertRedirect("/investor/campaigns/{$campaign->id}")
            ->assertSessionHas('status', 'You pledged MVR 2,000.00. It is held in escrow until the campaign closes.');

        $pledge = Pledge::sole();
        $this->assertSame(['held', '2000.00', $investor->id], [$pledge->status, (string) $pledge->amount, $pledge->investor_id]);
        $this->assertSame(3_000_00, $this->balance($investor));
        $this->assertSame(2_000_00, app(WalletLedger::class)->balance(WalletAccount::escrowFor($campaign)));
        $this->assertSame(Campaign::STATUS_LIVE, $campaign->fresh()->status, 'Not paid out: still open.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'pledge.created', 'actor_id' => $investor->id]);
        $this->assertLedgerReconciles();
    }

    public function test_progress_updates_after_each_pledge_everywhere_it_is_shown(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 10_000);
        $first = $this->investor(topUpCents: 5_000_00);
        $second = $this->investor(topUpCents: 5_000_00);
        $this->pledge($first, $campaign, 2_500);
        $this->pledge($second, $campaign, 1_500);

        $this->actingAs($first)->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.data.0.raised', 4_000)->where('campaigns.data.0.investors', 2));
        $this->actingAs($first)->get("/investor/campaigns/{$campaign->id}")
            ->assertInertia(fn (Assert $page) => $page->where('campaign.raised', 4_000)
                ->where('pledging.remaining', '6000.00')->where('pledging.balance', '2500.00')->where('pledging.mine', '2500.00'));
        $this->actingAs($campaign->borrower)->get("/borrower/campaigns/{$campaign->id}")
            ->assertInertia(fn (Assert $page) => $page->where('funding.raised', 4_000)->where('funding.investors', 2)->where('funding.remaining', '6000.00'));
    }

    public function test_an_investor_can_pledge_to_several_campaigns_and_more_than_once(): void
    {
        $investor = $this->investor(topUpCents: 5_000_00);
        [$a, $b] = [$this->liveCampaign(), $this->liveCampaign()];

        $this->pledge($investor, $a, 1_000)->assertSessionHasNoErrors();
        $this->pledge($investor, $b, 1_000)->assertSessionHasNoErrors();
        $this->pledge($investor, $a, 500)->assertSessionHasNoErrors();

        $this->assertSame(3, Pledge::where('investor_id', $investor->id)->count());
        $this->assertSame(2_500_00, $this->balance($investor));
        $this->assertLedgerReconciles();
    }

    /**
     * Wallet MVR 3,000; campaign target MVR 10,000 with MVR 8,000 already pledged (2,000 left).
     *
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'blank' => ['', 'Enter an amount.'],
            'letters' => ['lots', 'Enter an amount in MVR, like 5000 or 5000.50.'],
            'negative' => ['-100', 'Enter an amount in MVR, like 5000 or 5000.50.'],
            'below the minimum' => ['99.99', 'The smallest pledge is MVR 100.00.'],
            'more than is left' => ['2000.01', 'Only MVR 2,000.00 is left to raise. Pledge that or less.'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amounts_are_refused_and_nothing_moves(mixed $amount, string $message): void
    {
        $campaign = $this->liveCampaign(targetMvr: 10_000);
        app(PledgeService::class)->pledge($this->investor(topUpCents: 8_000_00), $campaign, 8_000_00);
        $investor = $this->investor(topUpCents: 3_000_00);

        $this->pledge($investor, $campaign, $amount)->assertSessionHasErrors(['amount' => $message]);
        $this->assertSame(3_000_00, $this->balance($investor));
        $this->assertSame(0, Pledge::where('investor_id', $investor->id)->count());
    }

    public function test_a_pledge_larger_than_the_wallet_is_refused(): void
    {
        $investor = $this->investor(topUpCents: 300_00);
        $campaign = $this->liveCampaign(targetMvr: 10_000);

        $this->pledge($investor, $campaign, 300.01)->assertSessionHasErrors(['amount' => 'Your wallet has MVR 300.00. Top up first, or pledge that or less.']);
        $this->pledge($investor, $campaign, 300)->assertSessionHasNoErrors();
        $this->assertSame(0, $this->balance($investor));
    }

    public function test_when_less_than_the_minimum_is_left_that_amount_can_be_pledged(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 10_000);
        app(PledgeService::class)->pledge($this->investor(topUpCents: 9_950_00), $campaign, 9_950_00);
        $investor = $this->investor(topUpCents: 1_000_00);

        $this->pledge($investor, $campaign, 49.99)->assertSessionHasErrors(['amount' => 'The smallest pledge is MVR 50.00.']);
        $this->pledge($investor, $campaign, 50)->assertSessionHasNoErrors();
        $this->assertSame(Campaign::STATUS_FUNDED, $campaign->fresh()->status);
    }

    public function test_the_pledge_that_reaches_the_target_funds_the_campaign_and_tells_everyone(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 10_000);
        $first = $this->investor(topUpCents: 10_000_00);
        $last = $this->investor(topUpCents: 10_000_00);
        $this->pledge($first, $campaign, 4_000);
        $this->pledge($first, $campaign, 1_000);

        $this->pledge($last, $campaign, 5_000)->assertSessionHas('status', 'You pledged MVR 5,000.00 and the campaign is now fully funded.');

        $campaign->refresh();
        $this->assertSame(Campaign::STATUS_FUNDED, $campaign->status);
        $this->assertNotNull($campaign->funded_at);
        $this->assertSame(2, Pledge::where('campaign_id', $campaign->id)->where('status', 'held')->distinct('investor_id')->count('investor_id'), 'Still held in escrow until PB-34 pays out.');
        Notification::assertSentTo($campaign->borrower, FundingOutcome::class, fn (FundingOutcome $n) => $n->outcome === FundingOutcome::FUNDED_BORROWER);
        Notification::assertSentTo($first, FundingOutcome::class, fn (FundingOutcome $n) => $n->outcome === FundingOutcome::FUNDED_INVESTOR && $n->cents === 5_000_00);
        Notification::assertSentTo($last, FundingOutcome::class, fn (FundingOutcome $n) => $n->cents === 5_000_00);
        $this->assertDatabaseHas('audit_logs', ['action' => 'campaign.funded']);

        // Funded: closed to further pledges, and gone from the marketplace.
        $this->pledge($this->investor(topUpCents: 1_000_00), $campaign, 100)->assertForbidden();
        $this->actingAs($first)->get('/investor/campaigns')->assertInertia(fn (Assert $page) => $page->where('campaigns.total', 0));
        $this->assertLedgerReconciles();
    }

    /**
     * @return array<string, array{0: callable(): User, 1: callable(): Campaign}>
     */
    public static function refusedByTheGate(): array
    {
        return [
            'unverified investor' => [function () {
                $user = User::factory()->investor()->create();
                InvestorProfile::factory()->for($user)->create(['risk_acknowledged_at' => now()]);

                return $user;
            }, fn () => Campaign::factory()->live()->create()],
            'risk acknowledgement not accepted' => [function () {
                $user = User::factory()->investor()->verified()->create();
                InvestorProfile::factory()->for($user)->create(['risk_acknowledged_at' => null]);

                return $user;
            }, fn () => Campaign::factory()->live()->create()],
            'Shariah-only, conventional campaign' => [function () {
                $user = User::factory()->investor()->verified()->create();
                InvestorProfile::factory()->for($user)->create(['risk_acknowledged_at' => now(), 'shariah_only' => true]);

                return $user;
            }, fn () => Campaign::factory()->conventional()->live()->create()],
            'deadline passed' => [fn () => null, fn () => Campaign::factory()->live(now()->subMinute())->create()],
            'not approved yet' => [fn () => null, fn () => Campaign::factory()->submitted()->create()],
        ];
    }

    #[DataProvider('refusedByTheGate')]
    public function test_the_pledge_gate_refuses(callable $who, callable $what): void
    {
        $investor = $who() ?? $this->investor();
        $campaign = $what();

        $this->pledge($investor, $campaign, 100)->assertForbidden();
        $this->assertSame(0, Pledge::count());
    }

    public function test_a_shariah_only_investor_can_pledge_to_a_shariah_campaign(): void
    {
        $investor = $this->investor(topUpCents: 1_000_00, shariahOnly: true);

        $this->pledge($investor, $this->liveCampaign(contract: 'murabahah'), 500)->assertSessionHasNoErrors();
        $this->pledge($investor, $this->liveCampaign(contract: 'mudarabah'), 500)->assertSessionHasNoErrors();
        $this->assertSame(0, $this->balance($investor));
    }

    public function test_only_investors_can_pledge(): void
    {
        $campaign = $this->liveCampaign();

        $this->post("/investor/campaigns/{$campaign->id}/pledges", ['amount' => 100])->assertRedirect('/login');
        $this->actingAs($campaign->borrower)->post("/investor/campaigns/{$campaign->id}/pledges", ['amount' => 100])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post("/investor/campaigns/{$campaign->id}/pledges", ['amount' => 100])->assertForbidden();
    }
}
