<?php

namespace Tests\Feature\Repayments;

use App\Console\Commands\AdvanceDemoSchedule;
use App\Models\BusinessReport;
use App\Models\Campaign;
use App\Models\User;
use App\Services\DemoClock;
use App\Support\SchedulePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-34: the demo time tool. Its rule (move every unpaid row by the same number of days so the
 * next one is due now or overdue) is tested on the DemoClock service directly. The tool itself,
 * the admin "Demo controls" page and the artisan command, must be unreachable outside the local
 * environment, which includes these tests (APP_ENV=testing).
 */
class DemoClockTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 10:00:00');
        Notification::fake();
    }

    public function test_it_makes_the_next_instalment_due_today_and_moves_the_rest_by_the_same_days(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000]); // due 26 Dec, 26 Jan, 26 Feb

        $moved = app(DemoClock::class)->advance($campaign);

        $this->assertSame(['days_moved' => -30, 'next_no' => 1, 'next_date' => '2026-11-26'], $moved);
        $this->assertSame(['2026-11-26', '2026-12-27', '2027-01-27'], $this->dates($campaign));
        $this->assertSame([SchedulePresenter::STATE_DUE, 'Due today'], $this->firstState($campaign));
        $this->assertDatabaseHas('audit_logs', ['action' => 'demo.schedule_moved', 'subject_id' => $campaign->id]);
    }

    public function test_it_makes_the_next_instalment_overdue(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000]);

        app(DemoClock::class)->advance($campaign, DemoClock::DEFAULT_OVERDUE_DAYS);

        $this->assertSame('2026-11-19', $this->dates($campaign)[0]);
        $this->assertSame([SchedulePresenter::STATE_LATE, '7 days late'], $this->firstState($campaign));
        // PB-35: the stored status follows at once, without waiting for repayments:mark-late.
        $this->assertSame('late', $campaign->repaymentSchedules()->where('instalment_no', 1)->value('status'));
    }

    public function test_moving_a_late_instalment_back_to_today_makes_it_due_again(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000]);
        app(DemoClock::class)->advance($campaign, 7);

        app(DemoClock::class)->advance($campaign, 0);

        $this->assertSame('due', $campaign->repaymentSchedules()->where('instalment_no', 1)->value('status'));
    }

    public function test_an_overdue_mudarabah_row_awaits_the_business_report_instead_of_being_late(): void
    {
        [$campaign] = $this->paidOutCampaign('mudarabah', 20_000, 3, [20_000]);

        app(DemoClock::class)->advance($campaign, 7);

        $this->assertSame([SchedulePresenter::STATE_AWAITING, SchedulePresenter::AWAITING_LABEL], $this->firstState($campaign));
    }

    public function test_paid_rows_keep_their_dates_and_the_next_unpaid_row_moves(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000]);
        $campaign->repaymentSchedules()->where('instalment_no', 1)->first()->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        $moved = app(DemoClock::class)->advance($campaign);

        $this->assertSame(2, $moved['next_no']);
        // Rows 2 and 3 both move back 61 days (26 Jan → 26 Nov), so the gap between them is kept.
        $this->assertSame(['2026-12-26', '2026-11-26', '2026-12-27'], $this->dates($campaign));
    }

    /**
     * @return array<string, array{0: callable, 1: int}>
     */
    public static function refusals(): array
    {
        return [
            'negative days' => [fn () => null, -1],
            'too many days' => [fn () => null, DemoClock::MAX_OVERDUE_DAYS + 1],
            'a campaign not paid out' => [fn () => Campaign::STATUS_LIVE, 0],
        ];
    }

    #[DataProvider('refusals')]
    public function test_invalid_requests_are_refused(callable $status, int $days): void
    {
        [$campaign] = $this->paidOutCampaign();
        if ($status() !== null) {
            $campaign->forceFill(['status' => $status()])->save();
        }
        $before = $this->dates($campaign);

        try {
            app(DemoClock::class)->advance($campaign, $days);
            $this->fail('The request should have been refused.');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, $this->dates($campaign));
        }
    }

    // ----- Unreachable outside local -----

    public function test_the_demo_controls_page_does_not_exist_outside_local(): void
    {
        [$campaign] = $this->paidOutCampaign();
        $admin = User::factory()->admin()->create();

        $this->assertFalse(app()->environment('local'));
        $this->assertFalse(Route::has('admin.demo.index'));
        $this->actingAs($admin)->get('/admin/demo')->assertNotFound();
        $this->actingAs($admin)->post("/admin/demo/campaigns/{$campaign->id}/advance", ['mode' => 'due'])->assertNotFound();
        $this->actingAs($admin)->post("/admin/demo/campaigns/{$campaign->id}/sample-report", ['outcome' => 'profit'])->assertNotFound();
        $this->actingAs($campaign->borrower)->get('/admin/demo')->assertNotFound();
        $this->assertSame(['2026-12-26', '2027-01-26', '2027-02-26'], $this->dates($campaign), 'Nothing moved.');
    }

    public function test_the_sidebar_link_is_hidden_outside_local(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')
            ->assertInertia(fn (Assert $page) => $page->where('demoTools', false));
    }

    public function test_the_artisan_command_refuses_to_run_outside_local(): void
    {
        [$campaign] = $this->paidOutCampaign();

        $this->artisan('demo:advance-schedule', ['campaign' => $campaign->id, '--overdue' => 7])
            ->expectsOutput(AdvanceDemoSchedule::ONLY_LOCAL)
            ->assertFailed();
        $this->assertSame(['2026-12-26', '2027-01-26', '2027-02-26'], $this->dates($campaign), 'Nothing moved.');
    }

    public function test_the_sample_report_command_refuses_to_run_outside_local(): void
    {
        [$campaign] = $this->paidOutCampaign('mudarabah', 10_000, 3, [10_000]);

        $this->artisan('demo:sample-report', ['campaign' => $campaign->id])
            ->expectsOutput(AdvanceDemoSchedule::ONLY_LOCAL)
            ->assertFailed();
        $this->assertSame(0, BusinessReport::count());
    }

    /**
     * @return list<string>
     */
    private function dates(Campaign $campaign): array
    {
        return $campaign->repaymentSchedules()->orderBy('instalment_no')->get()->map(fn ($row) => $row->due_date->toDateString())->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function firstState(Campaign $campaign): array
    {
        $row = collect(SchedulePresenter::forBorrower($campaign)['rows'])->firstWhere('state', '!=', SchedulePresenter::STATE_PAID);

        return [$row['state'], $row['stateLabel']];
    }
}
