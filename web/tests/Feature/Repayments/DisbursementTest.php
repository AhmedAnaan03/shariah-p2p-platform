<?php

namespace Tests\Feature\Repayments;

use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\RepaymentSchedule;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Notifications\CampaignDisbursed;
use App\Notifications\FundingOutcome;
use App\Services\DisbursementService;
use App\Services\FundingNotifier;
use App\Services\PledgeService;
use App\Services\SettlementService;
use App\Services\WalletLedger;
use App\Support\LedgerReconciler;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-34: when a campaign is funded, its escrow is paid out to the borrower as one balanced
 * transfer, its pledges are settled, and it moves to repaying with a schedule. The ledger still
 * reconciles, with the escrow at zero, and the borrower and investors are told.
 */
class DisbursementTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 10:00:00'); // 15:00 in Malé
        Notification::fake();
    }

    public function test_the_pledge_that_funds_a_campaign_pays_the_escrow_out_to_the_borrower_in_one_transfer(): void
    {
        [$campaign, [$alice, $bob]] = $this->paidOutCampaign('murabahah', 10_000, 3, [6_000, 4_000], ['markup_amount' => 1_000]);

        $legs = WalletTransaction::where('type', WalletTransaction::DISBURSEMENT)->orderBy('id')->get();
        $this->assertCount(2, $legs, 'One transfer: a debit and a credit.');
        $this->assertSame($legs[0]->transfer_ref, $legs[1]->transfer_ref);
        $this->assertSame(['debit', WalletAccount::TYPE_ESCROW, '10000.00', '0.00'], [$legs[0]->direction, $legs[0]->wallet_type, (string) $legs[0]->amount, (string) $legs[0]->balance_after]);
        $this->assertSame(['credit', WalletAccount::TYPE_USER, '10000.00'], [$legs[1]->direction, $legs[1]->wallet_type, (string) $legs[1]->amount]);
        $this->assertTrue($legs[1]->walletAccount->user->is($campaign->borrower));

        $this->assertSame(0, app(WalletLedger::class)->balance(WalletAccount::escrowFor($campaign)), 'The escrow is back to zero.');
        $this->assertSame(10_000_00, $this->balance($campaign->borrower));
        $this->assertSame([0, 0], [$this->balance($alice), $this->balance($bob)]);
        $this->assertSame(['settled', 'settled'], Pledge::where('campaign_id', $campaign->id)->orderBy('id')->pluck('status')->all());
        $this->assertLedgerReconciles();
    }

    public function test_the_campaign_moves_to_repaying_with_its_schedule_starting_one_month_after_the_payout(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000], ['markup_amount' => 1_000]);

        $this->assertSame(Campaign::STATUS_REPAYING, $campaign->status);
        $this->assertTrue($campaign->disbursed_at->equalTo(now()));
        $rows = $campaign->repaymentSchedules()->orderBy('instalment_no')->get();
        $this->assertSame(['2026-12-26', '2027-01-26', '2027-02-26'], $rows->map(fn ($r) => $r->due_date->toDateString())->all());
        $this->assertSame(['3333.33', '3333.33', '3333.34'], $rows->pluck('capital_due')->map(fn ($v) => (string) $v)->all());
        $this->assertSame(['333.33', '333.33', '333.34'], $rows->pluck('return_due')->map(fn ($v) => (string) $v)->all());
        $this->assertSame(['due', 'due', 'due'], $rows->pluck('status')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'campaign.disbursed', 'subject_id' => $campaign->id]);
    }

    public function test_a_mudarabah_schedule_has_no_return_figure(): void
    {
        [$campaign] = $this->paidOutCampaign('mudarabah', 20_000, 6, [20_000]);

        $this->assertSame(6, $campaign->repaymentSchedules()->count());
        $this->assertSame(0, $campaign->repaymentSchedules()->whereNotNull('return_due')->count(), 'NULL, not 0.');
        $this->assertSame('20000.00', (string) $campaign->repaymentSchedules()->sum('capital_due'));
        $this->assertLedgerReconciles();
    }

    public function test_paying_out_again_changes_nothing(): void
    {
        [$campaign] = $this->paidOutCampaign();
        $entries = WalletTransaction::count();

        $this->assertNull(app(DisbursementService::class)->disburse($campaign->id));
        $this->assertSame(0, app(DisbursementService::class)->disburseWaiting(), 'Nothing is waiting.');
        $this->assertSame($entries, WalletTransaction::count());
        $this->assertSame(3, $campaign->repaymentSchedules()->count());
        $this->assertLedgerReconciles();
    }

    public function test_the_deadline_job_pays_out_a_funded_campaign_still_waiting(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 5_000, contract: 'mudarabah');
        $investor = $this->investor(topUpCents: 5_000_00);
        $this->heldPledge($investor, $campaign, 5_000_00);
        $campaign->forceFill(['status' => Campaign::STATUS_FUNDED, 'funded_at' => now()])->save();

        $this->artisan('campaigns:settle-deadlines')->expectsOutput('Funded: 0. Not funded: 0. Pledges refunded: 0. Paid out: 1.')->assertSuccessful();

        $this->assertSame(Campaign::STATUS_REPAYING, $campaign->fresh()->status);
        $this->assertSame(5_000_00, $this->balance($campaign->borrower));
        $this->assertLedgerReconciles();
    }

    public function test_a_failed_payout_never_undoes_the_pledge_and_the_job_retries_it(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 5_000);
        $investor = $this->investor(topUpCents: 5_000_00);
        $failing = Mockery::mock(DisbursementService::class, [app(WalletLedger::class), app(FundingNotifier::class)])->makePartial();
        $failing->shouldReceive('disburse')->once()->andThrow(new RuntimeException('Payout failed.'));
        $this->app->instance(DisbursementService::class, $failing);

        app(PledgeService::class)->pledge($investor, $campaign, 5_000_00);

        $this->assertSame(Campaign::STATUS_FUNDED, $campaign->fresh()->status, 'The pledge stands: funded, waiting to be paid out.');
        $this->assertSame('held', Pledge::where('campaign_id', $campaign->id)->value('status'));
        $this->assertLedgerReconciles();

        $this->app->forgetInstance(DisbursementService::class);
        $this->app->forgetInstance(SettlementService::class);
        $this->artisan('campaigns:settle-deadlines')->expectsOutput('Funded: 0. Not funded: 0. Pledges refunded: 0. Paid out: 1.');
        $this->assertSame(Campaign::STATUS_REPAYING, $campaign->fresh()->status);
        $this->assertLedgerReconciles();
    }

    public function test_a_funded_campaign_whose_pledges_do_not_match_its_target_is_not_paid_out(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 5_000);
        $this->heldPledge($this->investor(topUpCents: 4_000_00), $campaign, 4_000_00);
        $campaign->forceFill(['status' => Campaign::STATUS_FUNDED, 'funded_at' => now()])->save();

        try {
            app(DisbursementService::class)->disburse($campaign->id);
            $this->fail('A payout short of the target must be refused.');
        } catch (LogicException) {
            // Rolled back: nothing moved.
        }

        $this->assertSame(Campaign::STATUS_FUNDED, $campaign->fresh()->status);
        $this->assertSame(0, WalletTransaction::where('type', WalletTransaction::DISBURSEMENT)->count());
        $this->assertSame(0, RepaymentSchedule::count());
        $this->assertLedgerReconciles();
    }

    public function test_only_a_funded_campaign_is_paid_out(): void
    {
        foreach ([Campaign::STATUS_LIVE, Campaign::STATUS_FAILED, Campaign::STATUS_SUSPENDED] as $status) {
            $campaign = $this->liveCampaign();
            $campaign->forceFill(['status' => $status])->save();

            $this->assertNull(app(DisbursementService::class)->disburse($campaign->id), $status);
        }
        $this->assertSame(0, WalletTransaction::where('type', WalletTransaction::DISBURSEMENT)->count());
    }

    // ----- Notifications -----

    public function test_the_borrower_and_each_investor_are_told_once_with_their_own_figures(): void
    {
        [$campaign, [$alice, $bob]] = $this->paidOutCampaign('murabahah', 10_000, 3, [6_000, 4_000], ['markup_amount' => 1_000]);

        // First instalment MVR 3,666.66: Alice (60%) 2,200.00, Bob (40%) 1,466.66; they add up exactly.
        Notification::assertSentToTimes($campaign->borrower, CampaignDisbursed::class, 1);
        Notification::assertSentTo($campaign->borrower, CampaignDisbursed::class, fn (CampaignDisbursed $n) => $n->audience === CampaignDisbursed::BORROWER
            && $n->firstCents === 3_666_66 && $n->firstDateLabel === '26 Dec 2026'
            && $n->toMail($campaign->borrower)->actionUrl === route('borrower.campaigns.show', $campaign));
        Notification::assertSentTo($alice, CampaignDisbursed::class, fn (CampaignDisbursed $n) => $n->pledgedCents === 6_000_00 && $n->firstCents === 2_200_00);
        Notification::assertSentTo($bob, CampaignDisbursed::class, fn (CampaignDisbursed $n) => $n->pledgedCents === 4_000_00 && $n->firstCents === 1_466_66);
        Notification::assertSentToTimes($alice, CampaignDisbursed::class, 1);
        Notification::assertNotSentTo([$campaign->borrower, $alice, $bob], FundingOutcome::class);
    }

    public function test_the_payout_messages_say_what_was_paid_and_when_the_schedule_starts(): void
    {
        [$campaign, [$investor]] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000], ['markup_amount' => 1_000, 'title' => 'Delivery van']);

        $borrower = new CampaignDisbursed($campaign, CampaignDisbursed::BORROWER, '26 Dec 2026', 3_666_66);
        $this->assertSame([
            'Your campaign "Delivery van" reached its target, and MVR 10,000.00 has been paid into your wallet to buy the asset.',
            'You pay the sale price of MVR 11,000.00 (the asset cost plus the fixed markup) in 3 monthly instalments. The first, of MVR 3,666.66, is due on 26 Dec 2026. The markup is fixed and can\'t change.',
        ], $borrower->lines());
        $mail = $borrower->toMail($campaign->borrower);
        $this->assertSame('Your campaign is funded and the money is in your wallet', $mail->subject);
        $this->assertContains(FundingOutcome::SIMULATED, $mail->introLines);

        $mail = (new CampaignDisbursed($campaign, CampaignDisbursed::INVESTOR, '26 Dec 2026', 3_666_66, 10_000_00))->toMail($investor);
        $this->assertSame('View the campaign', $mail->actionText);
        $this->assertSame(route('investor.campaigns.show', $campaign), $mail->actionUrl);
        $this->assertSame(['campaign_id', 'audience', 'first_date', 'first_amount', 'pledged', 'message'], array_keys((new CampaignDisbursed($campaign, CampaignDisbursed::INVESTOR, '26 Dec 2026', 1, 2))->toArray($investor)));
    }

    // ----- Reconciliation (rule 7) -----

    public function test_reconciliation_catches_a_payout_to_the_wrong_wallet(): void
    {
        [$campaign] = $this->paidOutCampaign();
        $stranger = $this->investor();
        $credit = WalletTransaction::where('type', WalletTransaction::DISBURSEMENT)->where('direction', 'credit')->first();
        DB::table('wallet_transactions')->where('id', $credit->id)->update(['wallet_account_id' => WalletAccount::forUser($stranger)->id]);

        $this->assertContains("Disbursement {$credit->transfer_ref} for campaign {$campaign->id} is not paid to that campaign's borrower.", LedgerReconciler::problems());
    }

    public function test_reconciliation_catches_settled_pledges_that_were_never_paid_out(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 1_000);
        $pledge = $this->heldPledge($this->investor(topUpCents: 1_000_00), $campaign, 1_000_00);
        $pledge->forceFill(['status' => 'settled'])->save();

        $problems = LedgerReconciler::problems();
        $this->assertContains("Campaign {$campaign->id} has settled pledges but was never paid out.", $problems);
        $this->assertContains("Campaign {$campaign->id}: escrow holds ".Money::fromCents(1_000_00).' but its held pledges total 0.00.', $problems);
    }

    public function test_the_reconcile_command_passes_after_payouts(): void
    {
        $this->paidOutCampaign('conventional');
        $this->paidOutCampaign('mudarabah');

        $this->artisan('wallet:reconcile')->assertSuccessful();
    }
}
