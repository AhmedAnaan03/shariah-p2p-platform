<?php

namespace App\Notifications;

use App\Models\BusinessReport;
use App\Models\User;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A Mudarabah business report was decided (PB-29): for the business (approved, or rejected with
 * the reason) or, once approved, for one investor with their own part of the period. Stored and
 * emailed.
 *
 * Wording: never "interest" or "guaranteed", and never a debt word (repay, instalment, due, owed,
 * late): a Mudarabah's capital is invested in the business and returned according to its results.
 *
 * $parts (cents, investors only): capital, restored, profit, loss.
 */
class BusinessReportDecided extends Notification
{
    public const BORROWER = 'borrower';

    public const INVESTOR = 'investor';

    /**
     * @param  array<string, int>  $parts
     */
    public function __construct(
        public readonly BusinessReport $report,
        public readonly string $audience,
        public readonly array $parts = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject())->greeting("Hello {$notifiable->name},");
        foreach ($this->lines() as $line) {
            $mail->line($line);
        }

        $campaign = $this->report->campaign;
        [$label, $url] = $this->audience === self::BORROWER
            ? ['View your campaign', route('borrower.campaigns.show', $campaign)]
            : ['View the campaign', route('investor.campaigns.show', $campaign)];

        return $mail->line(FundingOutcome::SIMULATED)->action($label, $url);
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        $report = $this->report;
        $title = $report->campaign->title;
        $period = $report->repaymentSchedule->instalment_no;
        $amount = fn (?string $value) => Money::format(abs(Money::fromDatabase($value)));
        $m = fn (string $key) => Money::format($this->parts[$key] ?? 0);

        if ($report->status === BusinessReport::STATUS_REJECTED) {
            return [
                "Your report for period {$period} of \"{$title}\" was not accepted. Reason given: {$report->decision_reason}",
                'No money has moved. Submit a new report for this period.',
            ];
        }

        $result = $report->isLoss()
            ? "a loss of {$amount($report->profit)}"
            : "a profit of {$amount($report->profit)}";

        if ($this->audience === self::BORROWER) {
            $lines = ["Your report for period {$period} of \"{$title}\" was approved: {$result}."];
            if (! $report->isLoss() && Money::fromDatabase($report->profit) > 0) {
                $lines[] = "Of the profit, {$amount($report->loss_restored)} restored earlier losses the investors bore, {$amount($report->investors_profit)} was the investors' share and {$amount($report->business_profit)} is the business's.";
            }
            $lines[] = "{$amount($report->capital_returned)} of capital was returned to the investors from your wallet.";

            return [...$lines, ...$this->lossLines()];
        }

        $lines = ["The business reported {$result} for period {$period} of \"{$title}\", and it was approved."];
        $received = ($this->parts['capital'] ?? 0) + ($this->parts['restored'] ?? 0) + ($this->parts['profit'] ?? 0);
        if ($received > 0) {
            $lines[] = 'You received '.Money::format($received).": {$m('capital')} of capital, {$m('restored')} restoring earlier losses and {$m('profit')} as your profit share.";
        }

        return [...$lines, ...$this->lossLines()];
    }

    /**
     * @return list<string>
     */
    private function lossLines(): array
    {
        $report = $this->report;
        if (! $report->isLoss()) {
            return [];
        }

        if ($report->loss_cause === BusinessReport::CAUSE_NEGLIGENCE) {
            return [
                'The loss was found to be caused by negligence or misconduct, so the business is liable for it and the investors\' capital is not reduced. Reason given: '.$report->decision_reason,
            ];
        }

        return $this->audience === self::BORROWER
            ? ['It was accepted as a genuine business loss: the investors bear '.Money::format(Money::fromDatabase($report->loss_borne)).' of it, in proportion to their capital.']
            : ['It was a genuine business loss, so you bear your share, '.Money::format($this->parts['loss'] ?? 0).', which reduces your capital. Later profit restores it before any profit is shared.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'campaign_id' => $this->report->campaign_id,
            'business_report_id' => $this->report->id,
            'audience' => $this->audience,
            'status' => $this->report->status,
            'parts' => array_map(fn (int $cents) => Money::fromCents($cents), $this->parts),
            'message' => $this->subject(),
        ];
    }

    public function subject(): string
    {
        if ($this->report->status === BusinessReport::STATUS_REJECTED) {
            return 'Your business report was not accepted';
        }

        return $this->audience === self::BORROWER ? 'Your business report was approved' : 'A Mudarabah you invested in has reported';
    }
}
