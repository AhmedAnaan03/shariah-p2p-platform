<?php

namespace Tests\Feature\Wallet;

use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\WalletTransaction;
use App\Notifications\FundingOutcome;
use App\Services\PledgeService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-20: the scheduled job settles campaigns at their deadline, all or nothing: target reached
 * means funded, otherwise every escrowed pledge is refunded. Borrower and investors are told.
 */
class SettlementTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 10:00:00');
        Notification::fake();
    }

    public function test_a_campaign_that_missed_its_target_refunds_every_pledge(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 10_000, deadline: now()->addHour());
        $alice = $this->investor(topUpCents: 5_000_00);
        $bilal = $this->investor(topUpCents: 5_000_00);
        $pledges = app(PledgeService::class);
        $pledges->pledge($alice, $campaign, 1_000_00);
        $pledges->pledge($alice, $campaign, 500_00);
        $pledges->pledge($bilal, $campaign, 2_000_00);

        Carbon::setTestNow(now()->addHours(2)); // the deadline passes
        $this->artisan('campaigns:settle-deadlines')->expectsOutput('Funded: 0. Not funded: 1. Pledges refunded: 3. Paid out: 0.')->assertSuccessful();

        $campaign->refresh();
        $this->assertSame(Campaign::STATUS_FAILED, $campaign->status);
        $this->assertSame(3, Pledge::where('campaign_id', $campaign->id)->where('status', 'refunded')->count());
        $this->assertSame(5_000_00, $this->balance($alice));
        $this->assertSame(5_000_00, $this->balance($bilal));
        $this->assertSame(3, WalletTransaction::where('type', 'pledge_refund')->where('direction', 'credit')->count());
        Notification::assertSentTo($campaign->borrower, FundingOutcome::class, fn (FundingOutcome $n) => $n->outcome === FundingOutcome::FAILED_BORROWER);
        Notification::assertSentTo($alice, FundingOutcome::class, fn (FundingOutcome $n) => $n->outcome === FundingOutcome::REFUNDED_INVESTOR && $n->cents === 1_500_00);
        Notification::assertSentTo($bilal, FundingOutcome::class, fn (FundingOutcome $n) => $n->cents === 2_000_00);
        Notification::assertSentToTimes($alice, FundingOutcome::class, 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'campaign.not_funded']);
        $this->assertLedgerReconciles();
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 10_000, deadline: now()->addHour());
        $investor = $this->investor(topUpCents: 1_000_00);
        app(PledgeService::class)->pledge($investor, $campaign, 1_000_00);
        Carbon::setTestNow(now()->addHours(2));

        $this->artisan('campaigns:settle-deadlines')->assertSuccessful();
        $this->artisan('campaigns:settle-deadlines')->expectsOutput('Funded: 0. Not funded: 0. Pledges refunded: 0. Paid out: 0.')->assertSuccessful();

        $this->assertSame(1, WalletTransaction::where('type', 'pledge_refund')->where('direction', 'credit')->count());
        $this->assertSame(1_000_00, $this->balance($investor));
        $this->assertLedgerReconciles();
    }

    public function test_a_campaign_with_no_pledges_is_simply_not_funded(): void
    {
        $campaign = $this->liveCampaign(deadline: now()->subMinute());

        $this->artisan('campaigns:settle-deadlines')->expectsOutput('Funded: 0. Not funded: 1. Pledges refunded: 0. Paid out: 0.');
        $this->assertSame(Campaign::STATUS_FAILED, $campaign->fresh()->status);
        Notification::assertSentTo($campaign->borrower, FundingOutcome::class);
    }

    public function test_a_fully_pledged_campaign_still_live_at_its_deadline_is_funded_and_paid_out(): void
    {
        // Normally the last pledge funds it at once; this covers one left live by any other path:
        // the whole target held in escrow, but the campaign still live.
        $campaign = $this->liveCampaign(targetMvr: 1_000, deadline: now()->addHour());
        $investor = $this->investor(topUpCents: 1_000_00);
        $this->heldPledge($investor, $campaign, 1_000_00);
        Carbon::setTestNow(now()->addHours(2));

        $this->artisan('campaigns:settle-deadlines')->expectsOutput('Funded: 1. Not funded: 0. Pledges refunded: 0. Paid out: 1.');
        $this->assertSame(Campaign::STATUS_REPAYING, $campaign->fresh()->status, 'PB-34: funded, then paid out in the same run.');
        $this->assertSame(0, $this->balance($investor), 'Nothing refunded.');
        $this->assertSame(1_000_00, $this->balance($campaign->borrower));
        $this->assertLedgerReconciles();
    }

    public function test_campaigns_before_their_deadline_or_not_live_are_left_alone(): void
    {
        $open = $this->liveCampaign(deadline: now()->addMinute());
        $submitted = Campaign::factory()->submitted()->create();
        $suspended = tap(Campaign::factory()->live(now()->subDay())->create(), fn ($c) => $c->forceFill(['status' => 'suspended'])->save());

        $this->artisan('campaigns:settle-deadlines')->expectsOutput('Funded: 0. Not funded: 0. Pledges refunded: 0. Paid out: 0.');

        $this->assertSame([Campaign::STATUS_LIVE, Campaign::STATUS_SUBMITTED, 'suspended'], [$open->fresh()->status, $submitted->fresh()->status, $suspended->fresh()->status]);
    }

    public function test_the_job_is_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'campaigns:settle-deadlines'));

        $this->assertNotNull($event, 'campaigns:settle-deadlines is scheduled.');
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_the_funding_emails_never_say_interest_or_guaranteed_and_say_the_money_is_simulated(): void
    {
        $campaign = Campaign::factory()->murabahah()->live()->create();
        foreach ([FundingOutcome::FAILED_BORROWER, FundingOutcome::REFUNDED_INVESTOR] as $outcome) {
            $mail = (new FundingOutcome($campaign, $outcome, 1_000_00))->toMail($campaign->borrower);
            $text = implode(' ', [$mail->subject, ...$mail->introLines, ...$mail->outroLines]);

            $this->assertDoesNotMatchRegularExpression('/\b(interest|guaranteed)\b/i', $text, $outcome);
            $this->assertStringContainsString('Simulated wallet, no real money.', $text, $outcome);
        }
    }
}
