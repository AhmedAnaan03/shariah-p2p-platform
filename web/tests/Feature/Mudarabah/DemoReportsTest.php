<?php

namespace Tests\Feature\Mudarabah;

use App\Models\BusinessReport;
use App\Models\User;
use App\Services\BusinessReportService;
use App\Services\DemoReports;
use App\Services\WalletLedger;
use App\Support\MudarabahCapital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-29 demo tool: a sample business report (a profit or a loss) for a Mudarabah's next period,
 * so every path can be shown quickly. Tested on the service; the route only exists locally
 * (DemoClockTest checks it is unreachable here).
 */
class DemoReportsTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 10:00:00');
        Notification::fake();
    }

    public function test_a_sample_profit_report_moves_the_period_to_today_and_can_be_approved(): void
    {
        [$campaign] = $this->paidOutCampaign('mudarabah', 10_000, 3, [10_000]);
        $admin = User::factory()->admin()->create();

        $result = app(DemoReports::class)->submitSample($campaign, DemoReports::PROFIT, $admin);

        $report = $result['report'];
        $this->assertTrue($result['moved'], 'Period 1 was planned for 26 Dec, so it was moved to today.');
        $this->assertSame(['1200.00', '800.00', '400.00', '3333.33'], [(string) $report->revenue, (string) $report->costs, (string) $report->profit, (string) $report->capital_returned]);
        $this->assertSame('2026-11-26', $report->repaymentSchedule->due_date->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'demo.sample_report', 'subject_id' => $report->id]);

        app(BusinessReportService::class)->approve($admin, $report);
        $this->assertLedgerReconciles();
    }

    public function test_a_sample_loss_report_can_be_found_genuine_or_negligent(): void
    {
        [$campaign] = $this->paidOutCampaign('mudarabah', 10_000, 3, [10_000]);
        $admin = User::factory()->admin()->create();

        $report = app(DemoReports::class)->submitSample($campaign, DemoReports::LOSS, $admin)['report'];
        $this->assertSame('-300.00', (string) $report->profit);

        app(BusinessReportService::class)->approve($admin, $report, BusinessReport::CAUSE_BUSINESS);
        $this->assertSame(300_00, MudarabahCapital::totals(MudarabahCapital::accounts($campaign))['loss']);
    }

    public function test_the_final_sample_settles_the_capital_and_tops_up_a_wallet_that_is_short(): void
    {
        [$campaign] = $this->paidOutCampaign('mudarabah', 10_000, 1, [10_000]); // one period: the final one
        app(WalletLedger::class)->withdraw($campaign->borrower, 10_000_00);
        $admin = User::factory()->admin()->create();

        $result = app(DemoReports::class)->submitSample($campaign, DemoReports::PROFIT, $admin);

        $this->assertSame('10000.00', (string) $result['report']->capital_returned, 'The final period returns all remaining capital.');
        $this->assertSame(10_400_00, $result['toppedUp'], 'Capital plus the whole profit, the most approval could pay.');
        app(BusinessReportService::class)->approve($admin, $result['report']);
        $this->assertSame('completed', $campaign->fresh()->status);
        $this->assertLedgerReconciles();
    }

    public function test_a_sample_is_refused_for_a_contract_without_reports(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only a Mudarabah has business reports.');
        app(DemoReports::class)->submitSample($campaign, DemoReports::PROFIT);
    }
}
