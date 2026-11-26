<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\User;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A campaign is finished (PB-36, PB-29): every instalment paid, or a Mudarabah's capital
 * settled. For the borrower or one investor, with their own totals. Stored and emailed.
 *
 * Shariah wording: a Murabahah never says "interest", and a Mudarabah never reads as a debt
 * (no "repaid", "instalment", "due" or "owed"): its capital was returned or settled.
 *
 * $totals (cents):
 *  - conventional and Murabahah: capital, return, total; for the borrower also waived (interest
 *    not charged on an early payoff).
 *  - Mudarabah: capital (returned), profit (shared with the investors, or the investor's share),
 *    restored and loss (genuine losses borne, and restored from later profit); for an investor
 *    also pledged.
 */
class CampaignCompleted extends Notification
{
    public const BORROWER = 'borrower';

    public const INVESTOR = 'investor';

    /**
     * @param  array<string, int>  $totals
     */
    public function __construct(
        public readonly Campaign $campaign,
        public readonly string $audience,
        public readonly array $totals,
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

        [$label, $url] = $this->audience === self::BORROWER
            ? ['View your campaign', route('borrower.campaigns.show', $this->campaign)]
            : ['View the campaign', route('investor.campaigns.show', $this->campaign)];

        return $mail->line(FundingOutcome::SIMULATED)->action($label, $url);
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        $title = $this->campaign->title;
        $m = fn (string $key) => Money::format($this->totals[$key] ?? 0);

        if ($this->campaign->isMudarabah()) {
            $losses = ($this->totals['loss'] ?? 0) > 0
                ? ["Genuine business losses of {$m('loss')} were borne by the capital, of which {$m('restored')} was restored from later profit."]
                : [];

            return $this->audience === self::BORROWER
                ? [
                    "The investors' capital in \"{$title}\" is settled.",
                    "In total the business returned {$m('capital')} of capital and paid {$m('profit')} as the investors' profit share.",
                    ...$losses,
                ]
                : [
                    "\"{$title}\" is settled. You invested {$m('pledged')}.",
                    "You received {$m('capital')} of capital back and {$m('profit')} as your profit share.",
                    ...(($this->totals['loss'] ?? 0) > 0
                        ? ["Genuine business losses reduced your capital by {$m('loss')}, of which {$m('restored')} was restored from later profit."]
                        : []),
                ];
        }

        $returnWord = $this->campaign->contract_type === Campaign::CONTRACT_MURABAHAH ? 'markup' : 'interest';

        if ($this->audience === self::BORROWER) {
            $lines = $this->campaign->contract_type === Campaign::CONTRACT_MURABAHAH
                ? ["You have paid the full sale price of \"{$title}\": {$m('total')}, the asset cost of {$m('capital')} plus the fixed markup of {$m('return')}."]
                : ["You have repaid \"{$title}\" in full: {$m('total')}, made up of {$m('capital')} capital and {$m('return')} interest."];
            if (($this->totals['waived'] ?? 0) > 0) {
                $lines[] = "Paying off early meant {$m('waived')} of interest for later periods wasn't charged.";
            }
            $lines[] = 'There were no late fees or penalty charges.';

            return $lines;
        }

        return [
            "\"{$title}\" is fully repaid.",
            "You received {$m('total')} in total: {$m('capital')} capital and {$m('return')} {$returnWord}.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'audience' => $this->audience,
            'totals' => array_map(fn (int $cents) => Money::fromCents($cents), $this->totals),
            'message' => $this->subject(),
        ];
    }

    public function subject(): string
    {
        if ($this->campaign->isMudarabah()) {
            return $this->audience === self::BORROWER ? 'The capital of your Mudarabah is settled' : 'A Mudarabah you invested in is settled';
        }

        return $this->audience === self::BORROWER ? 'Your campaign is paid in full' : 'A campaign you invested in is fully repaid';
    }
}
