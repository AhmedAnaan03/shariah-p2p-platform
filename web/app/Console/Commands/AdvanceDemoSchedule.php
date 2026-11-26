<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\DemoClock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * DEMO TOOL, local environment only (PB-34): make a repaying campaign's next unpaid row due now,
 * or overdue, so repayments and late payments can be shown without waiting a month.
 *
 *   php artisan demo:advance-schedule 12              next row due today
 *   php artisan demo:advance-schedule 12 --overdue=7  next row 7 days overdue
 *
 * Refuses to run anywhere but APP_ENV=local, so it can't touch real schedules in production and
 * never runs in the tests.
 */
#[Signature('demo:advance-schedule {campaign : The campaign id} {--overdue=0 : Days overdue (0 = due today)}')]
#[Description('DEMO TOOL (local only): move a campaign\'s schedule so its next row is due now or overdue')]
class AdvanceDemoSchedule extends Command
{
    public const ONLY_LOCAL = 'The demo time tool only runs in the local environment (APP_ENV=local).';

    public function handle(DemoClock $clock): int
    {
        if (! app()->environment('local')) {
            $this->error(self::ONLY_LOCAL);

            return self::FAILURE;
        }

        $campaign = Campaign::find($this->argument('campaign'));
        if ($campaign === null) {
            $this->error('No campaign has that id.');

            return self::FAILURE;
        }

        try {
            $moved = $clock->advance($campaign, (int) $this->option('overdue'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->warn('DEMO TOOL: schedule dates changed for demonstration.');
        $this->info("\"{$campaign->title}\": row {$moved['next_no']} is now dated {$moved['next_date']} (moved {$moved['days_moved']} days).");

        return self::SUCCESS;
    }
}
