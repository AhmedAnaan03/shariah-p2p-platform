<?php

namespace App\Console\Commands;

use App\Exceptions\ReportRefused;
use App\Models\Campaign;
use App\Services\DemoReports;
use App\Support\Money;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * DEMO TOOL, local environment only (PB-29): submit a sample business report for a Mudarabah's
 * next period, a profit or (with --loss) a loss, the same as the Demo controls page. Decide it on
 * the admin "Business reports" page.
 *
 *   php artisan demo:sample-report 12          a profit
 *   php artisan demo:sample-report 12 --loss   a loss
 */
#[Signature('demo:sample-report {campaign : The campaign id} {--loss : A loss instead of a profit}')]
#[Description('DEMO TOOL (local only): submit a sample Mudarabah business report for the next period')]
class SampleDemoReport extends Command
{
    public function handle(DemoReports $demo): int
    {
        if (! app()->environment('local')) {
            $this->error(AdvanceDemoSchedule::ONLY_LOCAL);

            return self::FAILURE;
        }

        $campaign = Campaign::find($this->argument('campaign'));
        if ($campaign === null) {
            $this->error('No campaign has that id.');

            return self::FAILURE;
        }

        try {
            $result = $demo->submitSample($campaign, $this->option('loss') ? DemoReports::LOSS : DemoReports::PROFIT);
        } catch (InvalidArgumentException|ReportRefused $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $report = $result['report'];
        $this->warn('DEMO TOOL: sample business report submitted.');
        $this->info("\"{$campaign->title}\": period {$report->repaymentSchedule->instalment_no}, ".($report->isLoss() ? 'loss' : 'profit').' of '.Money::format(abs(Money::fromDatabase($report->profit)))
            .($result['moved'] ? ' (period moved to today)' : '').($result['toppedUp'] > 0 ? ' (wallet topped up '.Money::format($result['toppedUp']).')' : '').'.');
        $this->info('Review it at '.route('admin.reports.show', $report));

        return self::SUCCESS;
    }
}
