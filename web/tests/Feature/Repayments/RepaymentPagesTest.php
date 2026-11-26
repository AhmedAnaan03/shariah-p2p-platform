<?php

namespace Tests\Feature\Repayments;

use App\Models\Campaign;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\RepaymentService;
use App\Services\WalletLedger;
use App\Support\RepaymentPresenter;
use App\Support\SchedulePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-35, PB-36 through HTTP: who may pay, what the forms accept, and what the borrower's and
 * investors' pages show (due, paid or late, the payoff and what each investor received).
 */
class RepaymentPagesTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    private Campaign $campaign;

    /** @var list<User> */
    private array $investors;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 10:00:00');
        Notification::fake();
        [$this->campaign, $this->investors] = $this->paidOutCampaign('murabahah', 10_000, 3, [6_000, 4_000], ['markup_amount' => 1_000]);
        app(WalletLedger::class)->topUp($this->campaign->borrower, 1_000_00);
    }

    private function row(int $no): RepaymentSchedule
    {
        return RepaymentSchedule::where('campaign_id', $this->campaign->id)->where('instalment_no', $no)->firstOrFail();
    }

    private function payUrl(): string
    {
        return "/borrower/campaigns/{$this->campaign->id}/repayments";
    }

    public function test_the_borrower_pays_the_next_instalment_from_the_campaign_page(): void
    {
        $this->actingAs($this->campaign->borrower)->from("/borrower/campaigns/{$this->campaign->id}")
            ->post($this->payUrl(), ['schedule_id' => $this->row(1)->id])
            ->assertRedirect("/borrower/campaigns/{$this->campaign->id}")
            ->assertSessionHas('status', 'You paid MVR 3,666.66. It has been shared out to the investors.');

        $this->assertSame('paid', $this->row(1)->status);
        $this->assertLedgerReconciles();
    }

    public function test_a_second_submission_of_the_same_instalment_is_refused_with_a_notice_and_charges_once(): void
    {
        $borrower = $this->campaign->borrower;
        $this->actingAs($borrower)->post($this->payUrl(), ['schedule_id' => $this->row(1)->id]);
        $balance = $this->balance($borrower);

        $this->actingAs($borrower)->post($this->payUrl(), ['schedule_id' => $this->row(1)->id])
            ->assertSessionHas('notice', 'Instalment 1 is already paid. Nothing more was taken from your wallet.');

        $this->assertSame($balance, $this->balance($borrower));
        $this->assertSame(1, Repayment::count());
    }

    public function test_a_wallet_too_low_gets_a_notice_and_nothing_is_paid(): void
    {
        app(WalletLedger::class)->withdraw($this->campaign->borrower, $this->balance($this->campaign->borrower) - 100_00);

        $this->actingAs($this->campaign->borrower)->post($this->payUrl(), ['schedule_id' => $this->row(1)->id])
            ->assertSessionHas('notice', 'Your wallet doesn’t hold enough for this instalment. Top up your wallet, then pay it.');
        $this->assertSame(0, Repayment::count());
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function invalidScheduleIds(): array
    {
        return [
            'TC1 blank' => ['', 'Choose the instalment to pay.'],
            'TC2 not a number' => ['abc', 'Choose the instalment to pay.'],
            'TC3 zero' => [0, 'Choose the instalment to pay.'],
        ];
    }

    #[DataProvider('invalidScheduleIds')]
    public function test_the_instalment_is_validated(mixed $value, string $message): void
    {
        $this->actingAs($this->campaign->borrower)->post($this->payUrl(), ['schedule_id' => $value])->assertSessionHasErrors(['schedule_id' => $message]);
        $this->assertSame(0, Repayment::count());
    }

    public function test_paying_off_takes_the_amount_shown_and_completes_the_campaign(): void
    {
        $quote = RepaymentService::quote($this->campaign);

        $this->actingAs($this->campaign->borrower)->post("/borrower/campaigns/{$this->campaign->id}/payoff", ['amount' => '11000.00'])
            ->assertSessionHas('status', 'You paid off MVR 11,000.00 (3 instalments). This campaign is now paid in full.');

        $this->assertSame(1_100_000, $quote['total']);
        $this->assertSame(Campaign::STATUS_COMPLETED, $this->campaign->fresh()->status);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidPayoffAmounts(): array
    {
        return ['TC4 blank' => [''], 'TC5 text' => ['all of it'], 'TC6 zero' => ['0']];
    }

    #[DataProvider('invalidPayoffAmounts')]
    public function test_the_payoff_amount_is_validated(mixed $value): void
    {
        $this->actingAs($this->campaign->borrower)->post("/borrower/campaigns/{$this->campaign->id}/payoff", ['amount' => $value])
            ->assertSessionHasErrors(['amount' => 'The payoff amount is missing. Reload the page and try again.']);
        $this->assertSame(0, Repayment::count());
    }

    public function test_only_the_campaigns_own_verified_borrower_can_pay(): void
    {
        $id = $this->row(1)->id;
        $other = User::factory()->borrower()->verified()->create();

        // Another borrower: 404, as if the campaign didn't exist. An investor or administrator: 403.
        $this->actingAs($other)->post($this->payUrl(), ['schedule_id' => $id])->assertNotFound();
        $this->actingAs($this->investors[0])->post($this->payUrl(), ['schedule_id' => $id])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post($this->payUrl(), ['schedule_id' => $id])->assertForbidden();
        // The owner, but no longer verified (e.g. sent back for checking): money can't move.
        $this->campaign->borrower->forceFill(['status' => 'pending_verification'])->save();
        $this->actingAs($this->campaign->borrower->fresh())->post($this->payUrl(), ['schedule_id' => $id])->assertForbidden();
        auth()->logout();
        $this->post($this->payUrl(), ['schedule_id' => $id])->assertRedirect('/login');

        $this->assertSame(0, Repayment::count());
    }

    public function test_the_borrower_page_shows_the_next_instalment_the_payoff_and_no_late_fees(): void
    {
        $this->actingAs($this->campaign->borrower)->get("/borrower/campaigns/{$this->campaign->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('repayment.next.label', 'Instalment 1')
                ->where('repayment.next.amount', '3666.66')
                ->where('repayment.next.stateLabel', 'Due 26 Dec 2026')
                ->where('repayment.payoff.total', '11000.00')
                ->where('repayment.payoff.explanation', RepaymentPresenter::MURABAHAH_PAYOFF)
                ->where('repayment.payoff.rows', [['Instalments settled', '3'], ['Remaining asset cost', 'MVR 10,000.00'], ['Remaining markup', 'MVR 1,000.00']])
                ->where('repayment.noFeesNote', SchedulePresenter::NO_FEES_NOTE)
                ->where('mudarabah', null));
    }

    public function test_a_conventional_payoff_dialog_shows_the_interest_not_charged(): void
    {
        [$conventional] = $this->paidOutCampaign('conventional', 10_000, 3, [10_000], ['interest_rate_percent' => 12]);

        $this->actingAs($conventional->borrower)->get("/borrower/campaigns/{$conventional->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('repayment.payoff.total', '10000.00')
                ->where('repayment.payoff.explanation', RepaymentPresenter::CONVENTIONAL_PAYOFF)
                ->where('repayment.payoff.rows.3', ['Interest not charged', 'MVR 200.67']));
    }

    public function test_borrower_and_investors_see_due_paid_and_late_for_every_instalment(): void
    {
        $borrower = $this->campaign->borrower;
        $this->actingAs($borrower)->post($this->payUrl(), ['schedule_id' => $this->row(1)->id]);
        Carbon::setTestNow('2027-01-29 10:00:00'); // instalment 2 (26 Jan) is 3 days late
        $this->artisan('repayments:mark-late');

        $this->assertSame(['paid', 'late', 'due'], RepaymentSchedule::where('campaign_id', $this->campaign->id)->orderBy('instalment_no')->pluck('status')->all());

        $states = fn (array $rows) => array_map(fn ($row) => [$row['state'], $row['stateLabel']], $rows);
        $expected = [['paid', 'Paid'], ['late', '3 days late'], ['upcoming', 'Due 26 Feb 2027']];

        $this->actingAs($borrower)->get("/borrower/campaigns/{$this->campaign->id}")
            ->assertInertia(fn (Assert $page) => $page->where('schedule.rows', fn ($rows) => $states($rows->all()) === $expected));
        $this->actingAs($this->investors[0])->get("/investor/campaigns/{$this->campaign->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('schedule.rows', fn ($rows) => $states($rows->all()) === $expected)
                ->where('schedule.rows.0.received', '2200.00')
                ->where('schedule.rows.1.received', null));
    }

    public function test_an_early_payoff_and_a_late_payment_are_labelled_on_the_schedule(): void
    {
        Carbon::setTestNow('2026-12-28 10:00:00'); // instalment 1 paid two days late
        app(RepaymentService::class)->payNext($this->campaign->borrower, $this->campaign, $this->row(1)->id);
        app(RepaymentService::class)->payOff($this->campaign->borrower, $this->campaign, RepaymentService::quote($this->campaign)['total']);

        $labels = array_column(SchedulePresenter::forBorrower($this->campaign->fresh())['rows'], 'stateLabel');
        $this->assertSame(['Paid late', 'Paid early', 'Paid early'], $labels);
    }
}
