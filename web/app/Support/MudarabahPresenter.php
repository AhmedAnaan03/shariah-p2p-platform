<?php

namespace App\Support;

use App\Exceptions\ReportRefused;
use App\Models\BusinessReport;
use App\Models\Campaign;
use App\Models\User;
use App\Services\BusinessReportService;
use App\Services\WalletLedger;

/**
 * A Mudarabah's capital account and period reports as its pages show them (PB-29): for the
 * business, for each investor (with their own part), and for the administrator deciding a report
 * (with a preview of each possible decision).
 *
 * Every label is here, so the wording tests can check them all: nothing says "interest" or
 * "guaranteed", and nothing reads as a debt (no instalment, due, owed, late or repayment). The
 * capital is invested in the business and returned according to its results.
 */
final class MudarabahPresenter
{
    /** How the period reports work, shown to the business, the investors and the administrator. */
    public const RULES_NOTE = 'For each planned capital return date, the business reports its revenue and costs. A profit first restores any genuine losses the investors bore earlier; what is left is shared at the agreed ratio. A genuine business loss is borne by the investors in proportion to their capital. A loss caused by negligence or misconduct is the business’s responsibility, so the capital stays to be restored. The last period settles the capital. No return is fixed in advance.';

    /** The capital account's figures. */
    public const ACCOUNT_LABELS = [
        'pledged' => 'Capital invested',
        'capital' => 'Capital returned',
        'profit' => 'Profit shared',
        'loss' => 'Genuine losses borne',
        'restored' => 'Losses restored from profit',
        'unrestored' => 'Losses still to restore',
        'outstanding' => 'Capital still invested',
    ];

    /** One approved report's outcome. */
    public const OUTCOME_LABELS = [
        'capital' => 'Capital returned',
        'restored' => 'Earlier losses restored',
        'investorsProfit' => 'Investors’ profit share',
        'businessProfit' => 'Business’s profit share',
        'lossBorne' => 'Loss borne by investors',
        'businessLiable' => 'Loss the business is responsible for',
        'outstandingAfter' => 'Capital still invested afterwards',
        'payment' => 'Paid to investors from the business’s wallet',
    ];

    public const CAUSE_LABELS = [
        BusinessReport::CAUSE_BUSINESS => 'Genuine business loss',
        BusinessReport::CAUSE_NEGLIGENCE => 'Negligence or misconduct',
    ];

    public const CAUSE_DESCRIPTIONS = [
        BusinessReport::CAUSE_BUSINESS => 'The investors bear it in proportion to their capital, which it reduces. Later profit restores it before any profit is shared.',
        BusinessReport::CAUSE_NEGLIGENCE => 'The business is responsible for it: the investors’ capital is not reduced and must still be returned in full. Give the reason; it is recorded and audit-logged.',
    ];

    public const REPORT_STATUS_LABELS = [
        BusinessReport::STATUS_SUBMITTED => 'Awaiting review',
        BusinessReport::STATUS_APPROVED => 'Approved',
        BusinessReport::STATUS_REJECTED => 'Not accepted',
    ];

    /**
     * The business's view: its capital account, the next period to report and every report.
     *
     * @return array<string, mixed>|null
     */
    public static function forBorrower(Campaign $campaign, User $borrower): ?array
    {
        if (! $campaign->isMudarabah() || $campaign->disbursed_at === null) {
            return null;
        }

        $period = BusinessReportService::nextPeriod($campaign, $borrower);
        $schedule = $period['schedule'];

        return [
            'note' => self::RULES_NOTE,
            'balance' => Money::fromCents(app(WalletLedger::class)->balanceFor($borrower)),
            'account' => self::account(MudarabahCapital::totals(MudarabahCapital::accounts($campaign))),
            'next' => $schedule === null ? null : [
                'no' => $schedule->instalment_no,
                'label' => "Period {$schedule->instalment_no}",
                'dateLabel' => $schedule->due_date->format('j M Y'),
                'allowed' => $period['refusal'] === null,
                'reason' => $period['refusal'],
                'createUrl' => route('borrower.campaigns.reports.create', $campaign),
            ],
            'reports' => self::reports($campaign),
        ];
    }

    /**
     * One investor's view: their own capital account, and their part of every approved report.
     *
     * @return array<string, mixed>|null
     */
    public static function forInvestor(Campaign $campaign, User $investor): ?array
    {
        if (! $campaign->isMudarabah() || $campaign->disbursed_at === null) {
            return null;
        }
        $accounts = MudarabahCapital::accounts($campaign);
        if (! isset($accounts[$investor->id])) {
            return null;
        }

        $parts = $campaign->investorPayouts()->where('investor_id', $investor->id)->whereNotNull('business_report_id')->get()->keyBy('business_report_id');

        return [
            'note' => self::RULES_NOTE,
            'account' => self::account($accounts[$investor->id]),
            'reports' => array_map(function (array $row) use ($parts) {
                $mine = $parts[$row['id']] ?? null;
                $row['yours'] = $row['status'] !== BusinessReport::STATUS_APPROVED ? null : [
                    'capital' => (string) ($mine?->capital_amount ?? '0.00'),
                    'restored' => (string) ($mine?->loss_restored_amount ?? '0.00'),
                    'profit' => (string) ($mine?->return_amount ?? '0.00'),
                    'loss' => (string) ($mine?->loss_amount ?? '0.00'),
                ];
                unset($row['document']); // only the business and administrators open documents

                return $row;
            }, array_values(array_filter(self::reports($campaign), fn (array $row) => $row['status'] === BusinessReport::STATUS_APPROVED))),
        ];
    }

    /**
     * The administrator's review page for one report: its figures, the capital account, and what
     * each possible decision would do.
     *
     * @return array<string, mixed>
     */
    public static function forAdmin(BusinessReport $report): array
    {
        $campaign = $report->campaign;
        $accounts = MudarabahCapital::accounts($campaign);
        $final = BusinessReportService::isFinal($report->repaymentSchedule);

        $preview = function (?string $cause) use ($report, $accounts, $campaign, $final) {
            try {
                $result = MudarabahSettlement::calculate($accounts, Money::fromDatabase($report->profit), Money::fromDatabase($report->capital_returned), $campaign->investorShareBasisPoints(), $cause, $final);
            } catch (ReportRefused $refused) {
                return ['allowed' => false, 'reason' => $refused->getMessage(), 'rows' => []];
            }

            return ['allowed' => true, 'reason' => null, 'rows' => self::outcomeRows($result)];
        };

        $loss = $report->isLoss();

        return [
            'report' => self::reportRow($report),
            'campaign' => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'business' => $campaign->borrower->borrowerProfile?->business_name ?? $campaign->borrower->name,
                'ratio' => self::ratio($campaign),
                'url' => route('admin.campaigns.show', $campaign),
            ],
            'final' => $final,
            'account' => self::account(MudarabahCapital::totals($accounts)),
            'businessBalance' => Money::fromCents(app(WalletLedger::class)->balanceFor($campaign->borrower)),
            'isLoss' => $loss,
            'previews' => $loss
                ? [BusinessReport::CAUSE_BUSINESS => $preview(BusinessReport::CAUSE_BUSINESS), BusinessReport::CAUSE_NEGLIGENCE => $preview(BusinessReport::CAUSE_NEGLIGENCE)]
                : ['approve' => $preview(null)],
            'note' => self::RULES_NOTE,
        ];
    }

    /**
     * "60 : 40 (investors : business)".
     */
    public static function ratio(Campaign $campaign): string
    {
        return $campaign->rateParts()[0].' (investors : business)';
    }

    /**
     * Every report of a campaign, newest period first.
     *
     * @return list<array<string, mixed>>
     */
    public static function reports(Campaign $campaign): array
    {
        return $campaign->businessReports()->with(['repaymentSchedule', 'document'])->orderByDesc('id')->get()
            ->map(fn (BusinessReport $report) => self::reportRow($report))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function reportRow(BusinessReport $report): array
    {
        $profit = Money::fromDatabase($report->profit);
        $approved = $report->status === BusinessReport::STATUS_APPROVED;

        return [
            'id' => $report->id,
            'period' => $report->repaymentSchedule->instalment_no,
            'periodLabel' => "Period {$report->repaymentSchedule->instalment_no}",
            'plannedDateLabel' => $report->repaymentSchedule->due_date->format('j M Y'),
            'submittedLabel' => Campaign::localDate($report->submitted_at),
            'status' => $report->status,
            'statusLabel' => self::REPORT_STATUS_LABELS[$report->status],
            'revenue' => (string) $report->revenue,
            'costs' => (string) $report->costs,
            'result' => Money::fromCents(abs($profit)),
            'resultLabel' => $profit > 0 ? 'Profit' : ($profit < 0 ? 'Loss' : 'No profit or loss'),
            'isLoss' => $profit < 0,
            'capitalReturned' => (string) $report->capital_returned,
            'causeLabel' => $report->loss_cause ? self::CAUSE_LABELS[$report->loss_cause] : null,
            'reason' => $report->decision_reason,
            'reviewedLabel' => $report->reviewed_at ? Campaign::localDate($report->reviewed_at) : null,
            'outcome' => $approved ? self::outcomeRows([
                'capital' => Money::fromDatabase($report->capital_returned),
                'restored' => Money::fromDatabase($report->loss_restored),
                'investorsProfit' => Money::fromDatabase($report->investors_profit),
                'businessProfit' => Money::fromDatabase($report->business_profit),
                'lossBorne' => Money::fromDatabase($report->loss_borne),
                'businessLiable' => Money::fromDatabase($report->business_liable_loss),
            ]) : [],
            'document' => $report->document?->toListItem(),
        ];
    }

    /**
     * An outcome as [label, amount] rows, leaving out the parts that are zero (except capital).
     *
     * @param  array<string, int>  $result
     * @return list<array{0: string, 1: string}>
     */
    private static function outcomeRows(array $result): array
    {
        $rows = [];
        foreach (self::OUTCOME_LABELS as $key => $label) {
            if (! array_key_exists($key, $result) || ($result[$key] === 0 && ! in_array($key, ['capital', 'outstandingAfter'], true))) {
                continue;
            }
            $rows[] = [$label, Money::fromCents($result[$key])];
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $account
     * @return list<array{key: string, label: string, value: string}>
     */
    private static function account(array $account): array
    {
        $rows = [];
        foreach (self::ACCOUNT_LABELS as $key => $label) {
            $rows[] = ['key' => $key, 'label' => $label, 'value' => Money::fromCents($account[$key])];
        }

        return $rows;
    }
}
