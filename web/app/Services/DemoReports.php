<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BusinessReport;
use App\Models\Campaign;
use App\Models\User;
use App\Support\Money;
use App\Support\MudarabahCapital;
use InvalidArgumentException;

/**
 * DEMO TOOL, local environment only (PB-29): submits a sample Mudarabah business report for the
 * next period, a profit or a loss, so every path (profit sharing, a genuine loss, negligence or
 * misconduct, rejection, restoring a loss from later profit, final settlement) can be shown in a
 * few clicks. The administrator then decides it on the normal review page.
 *
 * If the period's planned date is still ahead, the schedule is first moved so it falls today
 * (DemoClock, audit-logged). If the business's wallet couldn't pay what the report would pay the
 * investors, it is topped up with simulated money first, and the result says so.
 *
 * The figures, in proportion to the capital still invested:
 *  - profit: revenue 12%, costs 8%, so a 4% profit;
 *  - loss: revenue 5%, costs 8%, so a 3% loss.
 * The capital returned is the planned capital return, and on the last period whatever settles
 * the capital (less the loss, for a loss, which then has to be found genuine to settle it).
 */
class DemoReports
{
    public const PROFIT = 'profit';

    public const LOSS = 'loss';

    public function __construct(
        private readonly BusinessReportService $reports,
        private readonly DemoClock $clock,
        private readonly WalletLedger $ledger,
    ) {}

    /**
     * @return array{report: BusinessReport, moved: bool, toppedUp: int}
     */
    public function submitSample(Campaign $campaign, string $outcome, ?User $actor = null): array
    {
        if (! in_array($outcome, [self::PROFIT, self::LOSS], true)) {
            throw new InvalidArgumentException('Choose a profit or a loss.');
        }
        if (! $campaign->isMudarabah()) {
            throw new InvalidArgumentException('Only a Mudarabah has business reports.');
        }

        $borrower = $campaign->borrower;
        $period = BusinessReportService::nextPeriod($campaign, $borrower);
        $schedule = $period['schedule'];
        if ($schedule === null) {
            throw new InvalidArgumentException($period['refusal'] ?? 'There is no period to report.');
        }

        $moved = false;
        if ($schedule->due_date->toDateString() > Campaign::today()) {
            $this->clock->advance($campaign, 0, $actor);
            $moved = true;
        }

        $outstanding = MudarabahCapital::totals(MudarabahCapital::accounts($campaign))['outstanding'];
        $revenue = intdiv($outstanding * ($outcome === self::PROFIT ? 12 : 5), 100);
        $costs = intdiv($outstanding * 8, 100);
        $loss = max(0, $costs - $revenue);
        $capital = BusinessReportService::isFinal($schedule)
            ? $outstanding - $loss
            : min(Money::fromDatabase($schedule->capital_due), $outstanding);

        // The most the report could pay the investors: the capital plus all of the profit.
        $mostItPays = $capital + max(0, $revenue - $costs);
        $toppedUp = max(0, $mostItPays - $this->ledger->balanceFor($borrower));
        if ($toppedUp > 0) {
            $this->ledger->topUp($borrower, $toppedUp);
        }

        $report = $this->reports->submit($borrower, $campaign->fresh(), ['revenue' => $revenue, 'costs' => $costs, 'capital_returned' => $capital]);
        AuditLog::record('demo.sample_report', $report, [
            'outcome' => $outcome,
            'schedule_moved' => $moved,
            'topped_up' => Money::fromCents($toppedUp),
        ], $actor);

        return ['report' => $report, 'moved' => $moved, 'toppedUp' => $toppedUp];
    }
}
