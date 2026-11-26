<?php

namespace Tests\Feature\Repayments;

use App\Exceptions\InsufficientFunds;
use App\Exceptions\RepaymentRefused;
use App\Models\Campaign;
use App\Models\InvestorPayout;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Notifications\CampaignCompleted;
use App\Services\RepaymentService;
use App\Services\WalletLedger;
use App\Support\RepaymentHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-35: the borrower pays instalments from their wallet, in order, one at a time or all at once
 * early; each instalment is due, paid or late, and late is set automatically after the due date,
 * with no fee. Every payment is one balanced transfer into the escrow, paid straight out to the
 * investors (PB-36), and the ledger reconciles after each.
 *
 * The schedule used most: a MVR 10,000 Murabahah with a MVR 1,000 markup over 3 months, funded
 * 6,000 + 4,000, paid out on 26 Nov 2026. Instalments of 3,666.66, 3,666.66 and 3,666.68, due
 * 26 Dec 2026, 26 Jan 2027 and 26 Feb 2027.
 */
class RepaymentTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 10:00:00'); // 15:00 in Malé
        Notification::fake();
    }

    /**
     * @return array{0: Campaign, 1: list<User>}
     */
    private function murabahah(int $extraTopUpCents = 1_000_00): array
    {
        [$campaign, $investors] = $this->paidOutCampaign('murabahah', 10_000, 3, [6_000, 4_000], ['markup_amount' => 1_000]);
        if ($extraTopUpCents > 0) {
            app(WalletLedger::class)->topUp($campaign->borrower, $extraTopUpCents);
        }

        return [$campaign, $investors];
    }

    private function row(Campaign $campaign, int $no): RepaymentSchedule
    {
        return RepaymentSchedule::where('campaign_id', $campaign->id)->where('instalment_no', $no)->firstOrFail();
    }

    private function service(): RepaymentService
    {
        return app(RepaymentService::class);
    }

    public function test_paying_the_next_instalment_is_one_transfer_into_the_escrow_paid_straight_out_to_the_investors(): void
    {
        [$campaign, [$alice, $bob]] = $this->murabahah();

        $repayment = $this->service()->payNext($campaign->borrower, $campaign, $this->row($campaign, 1)->id);

        $this->assertSame(['3666.66', '3333.33', '333.33', 'instalment'], [(string) $repayment->amount, (string) $repayment->capital_amount, (string) $repayment->return_amount, $repayment->kind]);
        $legs = WalletTransaction::where('type', WalletTransaction::REPAYMENT)->orderBy('id')->get();
        $this->assertCount(2, $legs, 'One balanced transfer: a debit and a credit.');
        $this->assertSame([WalletAccount::TYPE_USER, 'debit', '3666.66'], [$legs[0]->wallet_type, $legs[0]->direction, (string) $legs[0]->amount]);
        $this->assertSame([WalletAccount::TYPE_ESCROW, 'credit'], [$legs[1]->wallet_type, $legs[1]->direction]);
        $this->assertSame($repayment->id, $legs[0]->repayment_id);

        // 60% and 40%: capital 2,000.00 + 1,333.33 (largest remainder), markup 200.00 + 133.33.
        $this->assertSame(2_200_00, $this->balance($alice));
        $this->assertSame(1_466_66, $this->balance($bob));
        $this->assertSame(0, app(WalletLedger::class)->balance(WalletAccount::escrowFor($campaign)), 'The escrow is back to zero.');
        $this->assertSame(10_000_00 + 1_000_00 - 3_666_66, $this->balance($campaign->borrower));
        $this->assertSame('paid', $this->row($campaign, 1)->status);
        $this->assertSame(Campaign::STATUS_REPAYING, $campaign->fresh()->status);
        $this->assertLedgerReconciles();
    }

    public function test_a_payment_the_wallet_cannot_cover_is_refused_and_nothing_is_written(): void
    {
        [$campaign] = $this->murabahah(extraTopUpCents: 0);
        app(WalletLedger::class)->withdraw($campaign->borrower, 10_000_00 - 1_000_00); // MVR 1,000 left

        try {
            $this->service()->payNext($campaign->borrower, $campaign, $this->row($campaign, 1)->id);
            $this->fail('A payment the wallet cannot cover must be refused.');
        } catch (InsufficientFunds) {
        }

        $this->assertSame(0, Repayment::count(), 'The repayment row was rolled back with the transfer.');
        $this->assertSame(0, InvestorPayout::count());
        $this->assertSame('due', $this->row($campaign, 1)->status);
        $this->assertSame(1_000_00, $this->balance($campaign->borrower));
        $this->assertLedgerReconciles();
    }

    public function test_instalments_are_paid_in_order(): void
    {
        [$campaign] = $this->murabahah();

        $this->expectException(RepaymentRefused::class);
        $this->expectExceptionMessage('Instalments are paid in order: instalment 1 is next.');
        $this->service()->payNext($campaign->borrower, $campaign, $this->row($campaign, 2)->id);
    }

    public function test_paying_the_same_instalment_twice_is_refused_and_charges_once(): void
    {
        [$campaign] = $this->murabahah();
        $first = $this->row($campaign, 1)->id;
        $this->service()->payNext($campaign->borrower, $campaign, $first);
        $balance = $this->balance($campaign->borrower);

        try {
            $this->service()->payNext($campaign->borrower, $campaign, $first);
            $this->fail('The second payment of the same instalment must be refused.');
        } catch (RepaymentRefused $e) {
            $this->assertSame('Instalment 1 is already paid. Nothing more was taken from your wallet.', $e->getMessage());
        }

        $this->assertSame($balance, $this->balance($campaign->borrower));
        $this->assertSame(1, Repayment::count());
        $this->assertLedgerReconciles();
    }

    public function test_only_the_campaigns_borrower_can_pay_and_a_mudarabah_has_no_instalments(): void
    {
        [$campaign] = $this->murabahah();
        [$mudarabah] = $this->paidOutCampaign('mudarabah', 10_000, 3, [10_000]);

        foreach ([[$mudarabah->borrower, $campaign, 'Only the borrower can repay this campaign.'], [$mudarabah->borrower, $mudarabah, 'A Mudarabah has no instalments: capital is returned through business reports.']] as [$who, $which, $message]) {
            try {
                $this->service()->payNext($who, $which, $this->row($which, 1)->id);
                $this->fail('Refused: '.$message);
            } catch (RepaymentRefused $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
        $this->assertSame(0, Repayment::count());
    }

    public function test_an_unpaid_instalment_becomes_late_after_its_due_date_and_a_mudarabah_never_does(): void
    {
        [$campaign] = $this->murabahah();
        [$mudarabah] = $this->paidOutCampaign('mudarabah', 10_000, 3, [10_000]);

        Carbon::setTestNow('2026-12-26 12:00:00'); // the due date itself, in Malé: due, not late yet
        $this->artisan('repayments:mark-late')->assertSuccessful();
        $this->assertSame('due', $this->row($campaign, 1)->status);

        Carbon::setTestNow('2026-12-26 19:00:00'); // 00:00 on 27 Dec in Malé
        $this->artisan('repayments:mark-late')->assertSuccessful();
        $this->assertSame(['late', 'due', 'due'], RepaymentSchedule::where('campaign_id', $campaign->id)->orderBy('instalment_no')->pluck('status')->all());
        $this->assertSame('due', $this->row($mudarabah, 1)->status, 'A planned capital return is never late.');
    }

    public function test_paying_a_late_instalment_costs_the_same_with_no_late_fee_and_counts_as_paid_late(): void
    {
        [$campaign] = $this->murabahah();
        Carbon::setTestNow('2027-01-05 10:00:00');
        RepaymentService::refreshLateStatus();
        $before = $this->balance($campaign->borrower);

        $repayment = $this->service()->payNext($campaign->borrower, $campaign, $this->row($campaign, 1)->id);

        $this->assertSame('3666.66', (string) $repayment->amount, 'No late fee or penalty: the scheduled amount only.');
        $this->assertSame($before - 3_666_66, $this->balance($campaign->borrower));
        $this->assertSame('paid', $this->row($campaign, 1)->status);
        $record = RepaymentHistory::for($campaign->borrower);
        $this->assertSame([1, 0, 1], [$record['instalmentsDue'], $record['paidOnTime'], $record['paidLate']]);
        $this->assertLedgerReconciles();
    }

    public function test_paying_the_last_instalment_completes_the_campaign_and_tells_everyone(): void
    {
        [$campaign, [$alice, $bob]] = $this->murabahah();
        foreach ([1, 2, 3] as $no) {
            $this->service()->payNext($campaign->borrower, $campaign, $this->row($campaign, $no)->id);
        }

        $campaign->refresh();
        $this->assertSame(Campaign::STATUS_COMPLETED, $campaign->status);
        $this->assertNotNull($campaign->completed_at);
        $this->assertSame([6_600_00, 4_400_00], [$this->balance($alice), $this->balance($bob)], 'Each got their share of the sale price, 60% and 40%.');

        Notification::assertSentTo($campaign->borrower, CampaignCompleted::class, function (CampaignCompleted $n) {
            return $n->lines()[0] === 'You have paid the full sale price of "'.$n->campaign->title.'": MVR 11,000.00, the asset cost of MVR 10,000.00 plus the fixed markup of MVR 1,000.00.'
                && in_array('There were no late fees or penalty charges.', $n->lines(), true);
        });
        Notification::assertSentTo($alice, CampaignCompleted::class, fn (CampaignCompleted $n) => $n->lines()[1] === 'You received MVR 6,600.00 in total: MVR 6,000.00 capital and MVR 600.00 markup.');
        Notification::assertSentTo($bob, CampaignCompleted::class, fn (CampaignCompleted $n) => $n->totals['total'] === 4_400_00);

        $this->expectException(RepaymentRefused::class);
        $this->service()->payNext($campaign->borrower, $campaign, $this->row($campaign, 3)->id);
    }

    public function test_a_murabahah_paid_off_early_pays_the_full_remaining_sale_price(): void
    {
        [$campaign, [$alice, $bob]] = $this->murabahah();
        $this->service()->payNext($campaign->borrower, $campaign, $this->row($campaign, 1)->id);

        $quote = RepaymentService::quote($campaign);
        $this->assertSame([666_667, 66_667, 0, 733_334], [$quote['capital'], $quote['return'], $quote['waived'], $quote['total']], 'Capital and markup in full; nothing waived.');

        $paid = $this->service()->payOff($campaign->borrower, $campaign, $quote['total']);

        $this->assertSame(2, $paid);
        $this->assertSame(['early_payoff', 'early_payoff'], Repayment::where('kind', 'early_payoff')->pluck('kind')->all());
        $this->assertSame(Campaign::STATUS_COMPLETED, $campaign->fresh()->status);
        $this->assertSame([6_600_00, 4_400_00], [$this->balance($alice), $this->balance($bob)]);
        $this->assertLedgerReconciles();
    }

    public function test_a_conventional_payoff_charges_the_remaining_capital_and_no_interest_for_periods_not_reached(): void
    {
        // MVR 10,000 at 12% a year over 3 months: instalments of capital plus 100.00, 67.00 and 33.67 interest.
        [$campaign, [$alice, $bob]] = $this->paidOutCampaign('conventional', 10_000, 3, [6_000, 4_000], ['interest_rate_percent' => 12]);
        app(WalletLedger::class)->topUp($campaign->borrower, 1_000_00);
        $rows = RepaymentSchedule::where('campaign_id', $campaign->id)->orderBy('instalment_no')->get();
        $interest = $rows->map(fn ($row) => (string) $row->return_due)->all();
        $this->assertSame(['100.00', '67.00', '33.67'], $interest);

        // On 10 Jan 2027 instalment 1 (26 Dec) is late; 2 and 3 are not reached yet.
        Carbon::setTestNow('2027-01-10 10:00:00');
        RepaymentService::refreshLateStatus();
        $quote = RepaymentService::quote($campaign);
        $this->assertSame(10_000_00, $quote['capital'], 'All remaining capital.');
        $this->assertSame(100_00, $quote['return'], 'Only the interest of the instalment already due.');
        $this->assertSame(67_00 + 33_67, $quote['waived'], 'Interest for periods not reached yet is not charged.');

        $this->service()->payOff($campaign->borrower, $campaign, $quote['total']);

        $this->assertSame(['100.00', '0.00', '0.00'], Repayment::orderBy('id')->pluck('return_amount')->map(fn ($v) => (string) $v)->all());
        // Investors are paid in proportion to what was actually paid: 60% and 40% of 10,100.
        $this->assertSame([6_060_00, 4_040_00], [$this->balance($alice), $this->balance($bob)]);
        $this->assertSame(Campaign::STATUS_COMPLETED, $campaign->fresh()->status);
        Notification::assertSentTo($campaign->borrower, CampaignCompleted::class, fn (CampaignCompleted $n) => in_array("Paying off early meant MVR 100.67 of interest for later periods wasn't charged.", $n->lines(), true));
        $this->assertLedgerReconciles();
    }

    public function test_a_payoff_whose_amount_changed_since_it_was_shown_is_refused(): void
    {
        [$campaign] = $this->paidOutCampaign('conventional', 10_000, 3, [10_000], ['interest_rate_percent' => 12]);
        $shown = RepaymentService::quote($campaign)['total'];

        Carbon::setTestNow('2026-12-27 10:00:00'); // instalment 1 is now due, so its interest is charged
        try {
            $this->service()->payOff($campaign->borrower, $campaign, $shown);
            $this->fail('A stale payoff amount must be refused.');
        } catch (RepaymentRefused $e) {
            $this->assertSame('The payoff amount is now MVR 10,100.00, not MVR 10,000.00. Check it and confirm again.', $e->getMessage());
        }
        $this->assertSame(0, Repayment::count());
    }
}
