<?php

namespace Tests\Feature\Repayments;

use App\Models\Campaign;
use App\Models\InvestorPayout;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\PledgeService;
use App\Services\RepaymentService;
use App\Services\WalletLedger;
use App\Support\InstalmentSplit;
use App\Support\LedgerReconciler;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-36: every repayment is paid straight out to the investors in the same transaction, split by
 * their share of the pledges, capital and return separately, with the rounding remainder handled
 * deterministically, so the shares always add up to exactly what was repaid.
 */
class PayoutTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 10:00:00');
        Notification::fake();
    }

    /**
     * Objective 4: one campaign funded by several investors, with every repayment allocated in
     * proportion to what each pledged, proven exactly and by wallet:reconcile.
     *
     * A MVR 10,000 Murabahah with a MVR 1,111 markup over 7 months, funded by four investors with
     * awkward shares (33.33%, 33.33%, 23.34%, 10%), so most instalments leave rounding remainders.
     */
    public function test_objective_4_several_investors_receive_returns_in_exact_proportion_to_their_pledges(): void
    {
        $pledges = [3_333, 3_333, 2_334, 1_000];
        [$campaign, $investors] = $this->paidOutCampaign('murabahah', 10_000, 7, $pledges, ['markup_amount' => 1_111]);
        app(WalletLedger::class)->topUp($campaign->borrower, 1_111_00);
        $weights = [];
        foreach ($investors as $i => $investor) {
            $weights[$investor->id] = $pledges[$i] * 100;
        }

        $expected = array_fill_keys(array_keys($weights), 0);
        $rows = RepaymentSchedule::where('campaign_id', $campaign->id)->orderBy('instalment_no')->get();
        foreach ($rows as $row) {
            // Worked out independently of the payout code: each part split on its own.
            $capital = InstalmentSplit::shares(Money::fromDatabase($row->capital_due), $weights);
            $return = InstalmentSplit::shares(Money::fromDatabase($row->return_due), $weights);
            foreach ($weights as $id => $weight) {
                $expected[$id] += $capital[$id] + $return[$id];
            }

            app(RepaymentService::class)->payNext($campaign->borrower, $campaign, $row->id);

            // After every instalment: the payouts add up to exactly what was repaid, by part.
            $paid = InvestorPayout::whereHas('repayment', fn ($q) => $q->where('repayment_schedule_id', $row->id));
            $this->assertSame(Money::fromDatabase($row->capital_due), Money::fromDatabase((clone $paid)->sum('capital_amount')), "Instalment {$row->instalment_no}: capital shares add up.");
            $this->assertSame(Money::fromDatabase($row->return_due), Money::fromDatabase((clone $paid)->sum('return_amount')), "Instalment {$row->instalment_no}: markup shares add up.");
        }

        $total = 11_111_00;
        $received = array_map(fn (User $investor) => $this->balance($investor), $investors);
        $this->assertSame($total, array_sum($received), 'Everything repaid reached the investors, to the laari.');
        foreach ($investors as $i => $investor) {
            $this->assertSame($expected[$investor->id], $received[$i]);
            // Within one laari per part per instalment of the exact proportion.
            $exact = $total * $pledges[$i] / 10_000;
            $this->assertEqualsWithDelta($exact, $received[$i], 2 * $rows->count(), "Investor {$i} receives their proportion.");
        }
        $this->assertSame(Campaign::STATUS_COMPLETED, $campaign->fresh()->status);
        $this->assertLedgerReconciles();
        $this->artisan('wallet:reconcile')->assertSuccessful();
    }

    public function test_the_rounding_remainder_goes_the_same_way_every_time(): void
    {
        // Three equal investors and a MVR 100.00 instalment: 33.34, 33.33, 33.33, the extra laari
        // to the lowest investor id on a tie.
        $this->assertSame([7 => 33_34, 8 => 33_33, 9 => 33_33], InstalmentSplit::shares(100_00, [7 => 1, 8 => 1, 9 => 1]));
        $this->assertSame(InstalmentSplit::shares(100_00, [9 => 1, 7 => 1, 8 => 1]), [9 => 33_33, 7 => 33_34, 8 => 33_33], 'Order of input does not matter.');
    }

    public function test_an_investor_who_pledged_twice_gets_one_payout_split_by_their_total(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 10_000, contract: 'murabahah');
        $alice = $this->investor(topUpCents: 6_000_00);
        $bob = $this->investor(topUpCents: 4_000_00);
        app(PledgeService::class)->pledge($alice, $campaign, 3_000_00);
        app(PledgeService::class)->pledge($bob, $campaign, 4_000_00);
        app(PledgeService::class)->pledge($alice, $campaign, 3_000_00);
        $campaign->refresh();
        app(WalletLedger::class)->topUp($campaign->borrower, 10_000_00);

        app(RepaymentService::class)->payNext($campaign->borrower, $campaign, RepaymentSchedule::where('campaign_id', $campaign->id)->orderBy('instalment_no')->value('id'));

        $this->assertSame(2, InvestorPayout::count(), 'One payout per investor, not per pledge.');
        $this->assertSame(60, (int) round(100 * $this->balance($alice) / ($this->balance($alice) + $this->balance($bob))));
        $this->assertLedgerReconciles();
    }

    public function test_the_reconciler_catches_a_payout_that_does_not_match_its_repayment(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [6_000, 4_000], ['markup_amount' => 1_000]);
        app(RepaymentService::class)->payNext($campaign->borrower, $campaign, RepaymentSchedule::where('campaign_id', $campaign->id)->orderBy('instalment_no')->value('id'));
        $this->assertLedgerReconciles();

        DB::table('investor_payouts')->where('id', InvestorPayout::min('id'))->update(['capital_amount' => DB::raw('capital_amount - 0.01'), 'return_amount' => DB::raw('return_amount + 0.01')]);

        $problems = LedgerReconciler::problems();
        $repaymentId = InvestorPayout::min('repayment_id');
        $this->assertContains("Repayment {$repaymentId}: its payouts don't add up to its capital and return exactly.", $problems);
        $this->artisan('wallet:reconcile')->assertFailed();
    }

    public function test_the_reconciler_catches_an_instalment_marked_paid_without_a_repayment(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000]);
        $row = RepaymentSchedule::where('campaign_id', $campaign->id)->orderBy('instalment_no')->first();
        DB::table('repayment_schedules')->where('id', $row->id)->update(['status' => 'paid']);

        $this->assertContains("Instalment {$row->id} is paid but has no repayment.", LedgerReconciler::problems());
    }
}
