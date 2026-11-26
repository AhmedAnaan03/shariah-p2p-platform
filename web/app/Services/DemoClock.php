<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\RepaymentSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * DEMO TOOL, local environment only (PB-34). Moves a repaying campaign's schedule forward in
 * time, so repayments and late payments can be demonstrated without waiting a month.
 *
 * It doesn't change the clock: it moves every unpaid row's date earlier by the same number of
 * days, so the next unpaid row is due today (or N days ago) and the gaps between rows stay the same.
 * Paid rows keep their dates. Every move is audit-logged with "demo" in its action.
 *
 * Only the admin "Demo controls" page and `php artisan demo:advance-schedule` call this, and both
 * exist only when APP_ENV is local (see routes/web.php and AdvanceDemoSchedule). The tests call
 * the service directly, so the rule it applies is covered without the tool being reachable.
 */
class DemoClock
{
    /** How overdue "make it overdue" makes the next row unless told otherwise. */
    public const DEFAULT_OVERDUE_DAYS = 7;

    public const MAX_OVERDUE_DAYS = 90;

    /**
     * Make the next unpaid row due $daysOverdue days ago (0 = today).
     *
     * @return array{days_moved: int, next_no: int, next_date: string}
     */
    public function advance(Campaign $campaign, int $daysOverdue = 0, ?User $actor = null): array
    {
        if ($daysOverdue < 0 || $daysOverdue > self::MAX_OVERDUE_DAYS) {
            throw new InvalidArgumentException('Days overdue must be from 0 to '.self::MAX_OVERDUE_DAYS.'.');
        }
        if ($campaign->status !== Campaign::STATUS_REPAYING) {
            throw new InvalidArgumentException('Only a campaign that has been paid out has a schedule to move.');
        }

        return DB::transaction(function () use ($campaign, $daysOverdue, $actor) {
            $unpaid = RepaymentSchedule::where('campaign_id', $campaign->id)->where('status', '!=', 'paid')
                ->orderBy('instalment_no')->lockForUpdate()->get();
            $next = $unpaid->first();
            if ($next === null) {
                throw new InvalidArgumentException('Every row of this schedule is already paid.');
            }

            $target = now()->setTimezone(Campaign::LOCAL_TIMEZONE)->startOfDay()->subDays($daysOverdue);
            // Negative: the dates move earlier. Positive only if the row was already more overdue.
            $days = (int) Carbon::parse($next->due_date->toDateString())->diffInDays(Carbon::parse($target->toDateString()), false);

            foreach ($unpaid as $row) {
                $row->due_date = $row->due_date->copy()->addDays($days);
                $row->save();
            }

            // PB-35: the late status follows the new dates at once, not a minute later.
            RepaymentService::refreshLateStatus($campaign);

            AuditLog::record('demo.schedule_moved', $campaign, [
                'days_moved' => $days,
                'next_instalment' => $next->instalment_no,
                'next_date' => $next->due_date->toDateString(),
            ], $actor);

            return ['days_moved' => $days, 'next_no' => $next->instalment_no, 'next_date' => $next->due_date->toDateString()];
        });
    }
}
