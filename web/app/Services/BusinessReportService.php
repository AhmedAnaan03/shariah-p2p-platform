<?php

namespace App\Services;

use App\Exceptions\InsufficientFunds;
use App\Exceptions\ReportRefused;
use App\Models\AuditLog;
use App\Models\BusinessReport;
use App\Models\Campaign;
use App\Models\InvestorPayout;
use App\Models\KycDocument;
use App\Models\Pledge;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Support\CampaignInvestors;
use App\Support\Money;
use App\Support\MudarabahCapital;
use App\Support\MudarabahSettlement;
use Illuminate\Http\UploadedFile;

/**
 * Mudarabah period reports (PB-29). For each planned capital return date the business reports
 * its revenue and costs (so its profit or loss) and the capital it returns; an administrator
 * approves or rejects it. Only approval moves money.
 *
 * Approval is one transaction (READ COMMITTED) that locks the campaign, then the report, then the
 * wallets, like every other money movement:
 *
 *  1. each investor's capital account is read from their pledges and payouts (MudarabahCapital);
 *  2. MudarabahSettlement works out the period: capital returned, earlier genuine losses restored
 *     from profit first, then the rest of the profit shared at the agreed ratio; or a loss,
 *     borne by the investors if genuine, or left with the business if caused by negligence or
 *     misconduct; and on the last period, that the capital is settled;
 *  3. the business pays the investors' part in one balanced `mudarabah_payment` transfer into the
 *     escrow (refused if its wallet holds too little), and the escrow pays each investor one
 *     `mudarabah_payout`. An `investor_payouts` row records each part separately, including a
 *     loss with no money;
 *  4. the report stores the outcome, the period is marked done, and when no capital is left
 *     invested the campaign is completed.
 *
 * The decision, its reason and the figures are audit-logged; the business and the investors are
 * told after the commit.
 */
class BusinessReportService
{
    public function __construct(
        private readonly WalletLedger $ledger,
        private readonly KycDocumentStore $documents,
        private readonly RepaymentNotifier $notifier,
    ) {}

    /**
     * The business submits its report for the next period.
     *
     * @param  array{revenue: int, costs: int, capital_returned: int}  $cents
     *
     * @throws ReportRefused
     */
    public function submit(User $borrower, Campaign $campaign, array $cents, ?UploadedFile $document = null): BusinessReport
    {
        return WalletLedger::run(function () use ($borrower, $campaign, $cents, $document) {
            $campaign = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $period = self::nextPeriod($campaign, $borrower);
            if ($period['refusal'] !== null) {
                throw new ReportRefused($period['refusal']);
            }
            $schedule = $period['schedule'];
            $profit = $cents['revenue'] - $cents['costs'];

            // The same rules approval applies, checked now so the business knows at once. A loss
            // is checked as genuine, the most the capital can be reduced by; the administrator
            // decides the cause.
            MudarabahSettlement::calculate(
                MudarabahCapital::accounts($campaign),
                $profit,
                $cents['capital_returned'],
                $campaign->investorShareBasisPoints(),
                $profit < 0 ? BusinessReport::CAUSE_BUSINESS : null,
                self::isFinal($schedule),
            );

            $report = new BusinessReport([
                'revenue' => Money::fromCents($cents['revenue']),
                'costs' => Money::fromCents($cents['costs']),
                'profit' => Money::fromCents($profit),
                'capital_returned' => Money::fromCents($cents['capital_returned']),
                'submitted_at' => now(),
            ]);
            $report->campaign()->associate($campaign);
            $report->repaymentSchedule()->associate($schedule);
            $report->submitter()->associate($borrower);
            if ($document !== null) {
                $report->document()->associate($this->documents->store($borrower, $document, KycDocument::TYPE_BUSINESS_REPORT, $campaign));
            }
            $report->save();

            AuditLog::record('business_report.submitted', $report, [
                'campaign_id' => $campaign->id,
                'period' => $schedule->instalment_no,
                'profit' => Money::fromCents($profit),
                'capital_returned' => Money::fromCents($cents['capital_returned']),
            ], $borrower);

            return $report;
        });
    }

    /**
     * The administrator rejects a report; the business can submit a new one for the period.
     *
     * @throws ReportRefused
     */
    public function reject(User $admin, BusinessReport $report, string $reason): BusinessReport
    {
        $report = WalletLedger::run(function () use ($admin, $report, $reason) {
            Campaign::whereKey($report->campaign_id)->lockForUpdate()->first();
            $report = $this->lockSubmitted($report);

            $report->forceFill([
                'status' => BusinessReport::STATUS_REJECTED,
                'decision_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
            AuditLog::record('business_report.rejected', $report, ['reason' => $reason], $admin);

            return $report;
        });

        $this->notifier->reportDecided($report);

        return $report;
    }

    /**
     * The administrator approves a report. For a loss, $lossCause says whether it was a genuine
     * business loss or caused by negligence or misconduct; $reason is required for the latter.
     *
     * @throws ReportRefused|InsufficientFunds
     */
    public function approve(User $admin, BusinessReport $report, ?string $lossCause = null, ?string $reason = null): BusinessReport
    {
        [$report, $completed] = WalletLedger::run(function () use ($admin, $report, $lossCause, $reason) {
            $campaign = Campaign::whereKey($report->campaign_id)->lockForUpdate()->firstOrFail();
            $report = $this->lockSubmitted($report);
            $loss = $report->isLoss();
            if (! $loss) {
                $lossCause = null;
            }
            if ($lossCause === BusinessReport::CAUSE_NEGLIGENCE && trim((string) $reason) === '') {
                throw new ReportRefused('Give the reason for finding negligence or misconduct.');
            }

            $schedule = $report->repaymentSchedule;
            $result = MudarabahSettlement::calculate(
                MudarabahCapital::accounts($campaign),
                Money::fromDatabase($report->profit),
                Money::fromDatabase($report->capital_returned),
                $campaign->investorShareBasisPoints(),
                $lossCause,
                self::isFinal($schedule),
            );

            $this->moveMoney($campaign, $report, $result);

            $report->forceFill([
                'status' => BusinessReport::STATUS_APPROVED,
                'loss_cause' => $lossCause,
                'decision_reason' => trim((string) $reason) === '' ? null : trim($reason),
                'loss_restored' => Money::fromCents($result['restored']),
                'investors_profit' => Money::fromCents($result['investorsProfit']),
                'business_profit' => Money::fromCents($result['businessProfit']),
                'loss_borne' => Money::fromCents($result['lossBorne']),
                'business_liable_loss' => Money::fromCents($result['businessLiable']),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
            $schedule->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

            AuditLog::record('business_report.approved', $report, [
                'campaign_id' => $campaign->id,
                'period' => $schedule->instalment_no,
                'profit' => (string) $report->profit,
                'loss_cause' => $lossCause,
                'reason' => $report->decision_reason,
                'capital_returned' => Money::fromCents($result['capital']),
                'loss_restored' => Money::fromCents($result['restored']),
                'investors_profit' => Money::fromCents($result['investorsProfit']),
                'business_profit' => Money::fromCents($result['businessProfit']),
                'loss_borne_by_investors' => Money::fromCents($result['lossBorne']),
                'loss_business_is_liable_for' => Money::fromCents($result['businessLiable']),
                'capital_still_invested' => Money::fromCents($result['outstandingAfter']),
            ], $admin);

            $completed = false;
            if ($result['outstandingAfter'] === 0) {
                $campaign->forceFill(['status' => Campaign::STATUS_COMPLETED, 'completed_at' => now()])->save();
                AuditLog::record('campaign.completed', $campaign, ['contract' => $campaign->contract_type, 'settled_by_report' => $report->id], $admin);
                $completed = true;
            }

            return [$report, $completed];
        });

        $this->notifier->reportDecided($report);
        if ($completed) {
            $this->notifier->completed($report->campaign->fresh());
        }

        return $report;
    }

    /**
     * The period the business reports on next, and why it can't yet if it can't.
     *
     * @return array{schedule: RepaymentSchedule|null, refusal: string|null}
     */
    public static function nextPeriod(Campaign $campaign, User $borrower): array
    {
        $refuse = fn (string $why) => ['schedule' => null, 'refusal' => $why];

        if ($campaign->borrower_id !== $borrower->id) {
            return $refuse('Only the business can report on this campaign.');
        }
        if (! $campaign->isMudarabah()) {
            return $refuse('Only a Mudarabah has business reports.');
        }
        if ($campaign->status === Campaign::STATUS_COMPLETED) {
            return $refuse('The capital is settled, so no more reports are needed.');
        }
        if ($campaign->status !== Campaign::STATUS_REPAYING) {
            return $refuse('Reports start once the capital has been paid out to the business.');
        }

        $schedule = RepaymentSchedule::where('campaign_id', $campaign->id)->where('status', '!=', 'paid')->orderBy('instalment_no')->first();
        if ($schedule === null) {
            return $refuse('Every period has been reported.');
        }
        if ($schedule->businessReports()->where('status', BusinessReport::STATUS_SUBMITTED)->exists()) {
            return ['schedule' => $schedule, 'refusal' => "The report for period {$schedule->instalment_no} is waiting for review."];
        }
        if ($schedule->due_date->toDateString() > Campaign::today()) {
            return ['schedule' => $schedule, 'refusal' => "The report for period {$schedule->instalment_no} can be submitted from its planned date, ".$schedule->due_date->format('j M Y').'.'];
        }

        return ['schedule' => $schedule, 'refusal' => null];
    }

    public static function isFinal(RepaymentSchedule $schedule): bool
    {
        return ! RepaymentSchedule::where('campaign_id', $schedule->campaign_id)->where('instalment_no', '>', $schedule->instalment_no)->exists();
    }

    private function lockSubmitted(BusinessReport $report): BusinessReport
    {
        $report = BusinessReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
        if ($report->status !== BusinessReport::STATUS_SUBMITTED) {
            throw new ReportRefused('This report has already been decided.');
        }

        return $report;
    }

    /**
     * Step 3: the business pays the investors' part into the escrow, and the escrow pays each
     * investor. Every investor with any part gets an investor_payouts row; one whose only part is
     * a genuine loss has no transfer.
     *
     * @param  array<string, mixed>  $result  from MudarabahSettlement::calculate()
     */
    private function moveMoney(Campaign $campaign, BusinessReport $report, array $result): void
    {
        $escrow = WalletAccount::escrowFor($campaign);
        $period = $report->repaymentSchedule->instalment_no;

        if ($result['payment'] > 0) {
            $this->ledger->transfer(
                WalletAccount::forUser($campaign->borrower),
                $escrow,
                $result['payment'],
                WalletTransaction::MUDARABAH_PAYMENT,
                description: "Period {$period} of \"{$campaign->title}\": capital and profit to investors",
                businessReport: $report,
            );
        }

        $pledges = Pledge::whereKey(CampaignInvestors::pledgeIds($campaign))->get()->keyBy('investor_id');
        foreach ($result['investors'] as $investorId => $parts) {
            $money = $parts['capital'] + $parts['restored'] + $parts['profit'];
            if ($money === 0 && $parts['loss'] === 0) {
                continue;
            }
            $pledge = $pledges[$investorId];
            $ref = $money === 0 ? null : $this->ledger->transfer(
                $escrow,
                WalletAccount::forUser($pledge->investor),
                $money,
                WalletTransaction::MUDARABAH_PAYOUT,
                $pledge,
                "Your part of period {$period} of \"{$campaign->title}\"",
                businessReport: $report,
            );
            InvestorPayout::create([
                'campaign_id' => $campaign->id,
                'investor_id' => $investorId,
                'pledge_id' => $pledge->id,
                'business_report_id' => $report->id,
                'capital_amount' => Money::fromCents($parts['capital']),
                'loss_restored_amount' => Money::fromCents($parts['restored']),
                'return_amount' => Money::fromCents($parts['profit']),
                'loss_amount' => Money::fromCents($parts['loss']),
                'transfer_ref' => $ref,
            ]);
        }
    }
}
