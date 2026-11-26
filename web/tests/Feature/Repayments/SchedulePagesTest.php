<?php

namespace Tests\Feature\Repayments;

use App\Models\Campaign;
use App\Models\User;
use App\Support\Money;
use App\Support\SchedulePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesWallets;
use Tests\Concerns\ShariahWording;
use Tests\TestCase;

/**
 * PB-34, PB-28: the borrower's request page shows the schedule; the investor's campaign page
 * shows it with their share of every row. A Mudarabah's schedule is planned capital returns:
 * never "instalment" or "late", and a passed date awaits the business's report.
 */
class SchedulePagesTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 10:00:00');
        Notification::fake();
    }

    public function test_the_borrower_sees_a_murabahah_schedule_that_adds_up_to_the_sale_price(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000], ['markup_amount' => 1_000]);

        $this->actingAs($campaign->borrower)->get("/borrower/campaigns/{$campaign->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Borrower/Campaigns/Show')
                ->where('campaign.badge_status', 'repaying')
                ->where('schedule.labels.row', 'Instalment')
                ->where('schedule.labels.return', 'Markup')
                ->where('schedule.count', 3)
                ->where('schedule.capitalTotal', '10000.00')
                ->where('schedule.returnTotal', '1000.00')
                ->where('schedule.total', '11000.00')
                ->where('schedule.firstDateLabel', '26 Dec 2026')
                ->where('schedule.disbursedOnLabel', '26 Nov 2026')
                ->where('schedule.rows.0.label', 'Instalment 1')
                ->where('schedule.rows.0.amount', '3666.66')
                ->where('schedule.rows.2.amount', '3666.68')
                ->where('schedule.rows.0.state', SchedulePresenter::STATE_UPCOMING)
                ->where('schedule.rows.0.stateLabel', 'Due 26 Dec 2026')
                ->where('funding.raised', 10_000));
    }

    public function test_a_conventional_schedule_names_interest_and_its_rows_add_up(): void
    {
        [$campaign] = $this->paidOutCampaign('conventional', 10_000, 12, [10_000], ['interest_rate_percent' => 12]);

        $schedule = $this->actingAs($campaign->borrower)->get("/borrower/campaigns/{$campaign->id}")->viewData('page')['props']['schedule'];

        $this->assertSame('Interest', $schedule['labels']['return']);
        $this->assertSame('10000.00', $schedule['capitalTotal']);
        $this->assertSame('661.86', $schedule['returnTotal']);
        $this->assertSame($schedule['total'], Money::fromCents(array_sum(array_map(fn ($r) => Money::toCents($r['amount']), $schedule['rows']))));
    }

    public function test_an_investor_sees_their_share_of_every_row_and_the_shares_add_up(): void
    {
        [$campaign, [$alice, $bob]] = $this->paidOutCampaign('murabahah', 10_000, 3, [6_000, 4_000], ['markup_amount' => 1_000]);

        $shares = [];
        foreach ([$alice, $bob] as $investor) {
            $props = $this->actingAs($investor)->get("/investor/campaigns/{$campaign->id}")->assertOk()->viewData('page')['props'];
            $shares[] = array_column($props['schedule']['rows'], 'share');
            $this->assertSame($investor->is($alice) ? '6000.00' : '4000.00', $props['schedule']['yourPledged']);
        }

        // Capital and markup are split separately (as PB-35 pays them), each to the cent: the last
        // instalment's 3,333.34 + 333.34 gives Alice 2,000.00 + 200.00 and Bob 1,333.34 + 133.34.
        $this->assertSame(['2200.00', '2200.00', '2200.00'], $shares[0], 'Alice: 60% of each instalment.');
        $this->assertSame(['1466.66', '1466.66', '1466.68'], $shares[1], 'Bob: 40%.');
        $rows = SchedulePresenter::forBorrower($campaign)['rows'];
        foreach ($rows as $i => $row) {
            $this->assertSame(Money::toCents($row['amount']), Money::toCents($shares[0][$i]) + Money::toCents($shares[1][$i]), "Row {$i}: the shares add up to the instalment.");
        }
    }

    public function test_the_schedule_is_absent_before_the_payout(): void
    {
        $campaign = Campaign::factory()->murabahah()->live()->create();

        $this->actingAs($campaign->borrower)->get("/borrower/campaigns/{$campaign->id}")
            ->assertInertia(fn (Assert $page) => $page->where('schedule', null));
    }

    // ----- Mudarabah: planned capital returns, not a debt -----

    public function test_a_mudarabah_schedule_is_labelled_planned_capital_return_with_no_return_figure(): void
    {
        [$campaign, [$investor]] = $this->paidOutCampaign('mudarabah', 20_000, 6, [20_000]);

        $this->actingAs($investor)->get("/investor/campaigns/{$campaign->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('standing.status', 'returning')
                ->where('standing.title', 'Funded and returning capital')
                ->where('schedule.labels.title', 'Planned capital return schedule')
                ->where('schedule.labels.row', 'Planned capital return')
                ->where('schedule.labels.date', 'Planned date')
                ->where('schedule.labels.return', null)
                ->where('schedule.returnTotal', null)
                ->where('schedule.rows.0.label', 'Planned capital return 1')
                ->where('schedule.rows.0.return', null)
                ->where('schedule.rows.0.stateLabel', 'Planned for 26 Dec 2026')
                ->where('schedule.note', fn (string $note) => str_contains($note, SchedulePresenter::MUDARABAH_CAPITAL_NOTE)));
        $this->assertSame('returning', $this->actingAs($campaign->borrower)->get("/borrower/campaigns/{$campaign->id}")->viewData('page')['props']['campaign']['badge_status']);
    }

    public function test_a_passed_mudarabah_date_awaits_the_business_report_and_is_never_late(): void
    {
        [$campaign] = $this->paidOutCampaign('mudarabah', 20_000, 6, [20_000]);
        Carbon::setTestNow('2027-01-05 10:00:00'); // the first planned date (26 Dec) has passed

        $rows = SchedulePresenter::forBorrower($campaign)['rows'];

        $this->assertSame([SchedulePresenter::STATE_AWAITING, SchedulePresenter::AWAITING_LABEL], [$rows[0]['state'], $rows[0]['stateLabel']]);
        $this->assertSame(SchedulePresenter::STATE_UPCOMING, $rows[1]['state']);
        $this->assertNotContains(SchedulePresenter::STATE_LATE, array_column($rows, 'state'));
    }

    public function test_a_passed_debt_instalment_is_late_by_its_days(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000]);

        Carbon::setTestNow('2026-12-26 10:00:00');
        $this->assertSame([SchedulePresenter::STATE_DUE, 'Due today'], $this->firstState($campaign));
        Carbon::setTestNow('2026-12-27 10:00:00');
        $this->assertSame([SchedulePresenter::STATE_LATE, '1 day late'], $this->firstState($campaign));
        Carbon::setTestNow('2027-01-05 10:00:00');
        $this->assertSame([SchedulePresenter::STATE_LATE, '10 days late'], $this->firstState($campaign));
    }

    public function test_the_mudarabah_schedule_wording_never_reads_as_a_debt(): void
    {
        [$campaign, [$investor]] = $this->paidOutCampaign('mudarabah', 20_000, 3, [20_000]);
        Carbon::setTestNow('2027-01-05 10:00:00');

        foreach ([SchedulePresenter::forBorrower($campaign), SchedulePresenter::forInvestor($campaign, $investor)] as $schedule) {
            $texts = [...array_filter(array_values($schedule['labels'])), $schedule['note'], ...array_column($schedule['rows'], 'label'), ...array_column($schedule['rows'], 'stateLabel')];
            foreach ($texts as $text) {
                $this->assertDoesNotMatchRegularExpression(ShariahWording::DEBT, $text);
                $this->assertDoesNotMatchRegularExpression(ShariahWording::FORBIDDEN, $text);
            }
        }
    }

    // ----- Role access -----

    public function test_another_borrower_cannot_see_the_schedule(): void
    {
        [$campaign] = $this->paidOutCampaign();

        $this->actingAs(User::factory()->borrower()->verified()->create())->get("/borrower/campaigns/{$campaign->id}")->assertNotFound();
    }

    public function test_an_investor_who_did_not_pledge_cannot_open_a_paid_out_campaign(): void
    {
        [$campaign] = $this->paidOutCampaign();

        $this->actingAs($this->investor())->get("/investor/campaigns/{$campaign->id}")->assertNotFound();
    }

    public function test_other_roles_cannot_use_the_borrower_or_investor_pages(): void
    {
        [$campaign, [$investor]] = $this->paidOutCampaign();
        $admin = User::factory()->admin()->create();

        $this->actingAs($investor)->get("/borrower/campaigns/{$campaign->id}")->assertForbidden();
        $this->actingAs($campaign->borrower)->get("/investor/campaigns/{$campaign->id}")->assertForbidden();
        $this->actingAs($admin)->get("/investor/campaigns/{$campaign->id}")->assertForbidden();
        $this->actingAs($admin)->get("/borrower/campaigns/{$campaign->id}")->assertForbidden();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function firstState(Campaign $campaign): array
    {
        $row = SchedulePresenter::forBorrower($campaign)['rows'][0];

        return [$row['state'], $row['stateLabel']];
    }
}
