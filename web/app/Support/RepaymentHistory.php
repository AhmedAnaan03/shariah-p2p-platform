<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\RepaymentSchedule;
use App\Models\User;

/**
 * A borrower's repayment record on the platform, for the campaign page (PB-17): how the
 * instalments of their earlier, funded campaigns have been paid. Only instalments already due
 * count; future ones say nothing yet about how the borrower pays.
 *
 * - Paid on time: paid on or before the due date (Maldives date).
 * - Paid late: paid after the due date.
 * - Overdue: due date passed and not paid (status late, or still due).
 *
 * The campaign being viewed is left out: it has no instalments until it is funded.
 */
final class RepaymentHistory
{
    /**
     * Campaign statuses that had money paid out to the borrower, so repayments are owed.
     */
    public const FUNDED_STATUSES = ['funded', 'repaying', 'completed', 'defaulted'];

    /**
     * @return array{fundedCampaigns: int, completedCampaigns: int, instalmentsDue: int, paidOnTime: int, paidLate: int, overdue: int, summary: string}
     */
    public static function for(User $borrower, ?Campaign $except = null): array
    {
        $campaigns = $borrower->campaigns()
            ->whereIn('status', self::FUNDED_STATUSES)
            ->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->get(['id', 'status']);

        $today = now()->setTimezone(Campaign::LOCAL_TIMEZONE)->toDateString();
        $schedules = RepaymentSchedule::whereIn('campaign_id', $campaigns->pluck('id'))
            ->whereDate('due_date', '<=', $today)
            ->get();

        $paidOnTime = $paidLate = $overdue = 0;
        foreach ($schedules as $schedule) {
            if ($schedule->status === 'paid' && $schedule->paid_at !== null) {
                $paidOn = $schedule->paid_at->copy()->setTimezone(Campaign::LOCAL_TIMEZONE)->toDateString();
                $paidOn <= $schedule->due_date->toDateString() ? $paidOnTime++ : $paidLate++;
            } elseif ($schedule->status === 'late' || $schedule->due_date->toDateString() < $today) {
                $overdue++;
            }
            // Otherwise it is due today and not paid yet: not late until tomorrow.
        }

        $record = [
            'fundedCampaigns' => $campaigns->count(),
            'completedCampaigns' => $campaigns->where('status', 'completed')->count(),
            'instalmentsDue' => $paidOnTime + $paidLate + $overdue,
            'paidOnTime' => $paidOnTime,
            'paidLate' => $paidLate,
            'overdue' => $overdue,
        ];

        return [...$record, 'summary' => self::summary($record)];
    }

    /**
     * One plain sentence for the page.
     *
     * @param  array<string, int>  $record
     */
    public static function summary(array $record): string
    {
        if ($record['fundedCampaigns'] === 0) {
            return 'No repayment history on Amanah yet: this borrower has not had a campaign funded here before.';
        }

        if ($record['instalmentsDue'] === 0) {
            return 'This borrower has had a campaign funded here, but no instalments have been due yet.';
        }

        $due = $record['instalmentsDue'];
        $paid = "{$record['paidOnTime']} of {$due} ".($due === 1 ? 'instalment' : 'instalments').' paid on time';
        $late = $record['paidLate'] > 0 ? ', '.$record['paidLate'].' paid late' : '';
        $overdue = $record['overdue'] > 0 ? ', '.$record['overdue'].' overdue now' : '';

        return "{$paid}{$late}{$overdue}.";
    }
}
