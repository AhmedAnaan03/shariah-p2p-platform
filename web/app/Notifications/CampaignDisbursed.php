<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\User;
use App\Support\Money;
use App\Support\SchedulePresenter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A campaign was funded and paid out to the borrower (PB-34), for the borrower or one investor.
 * Stored in the notifications table and emailed, like FundingOutcome. It replaces PB-20's
 * "fully funded" email: the payout follows the funding within moments, so one message says both.
 *
 * Shariah wording: never "interest" or "guaranteed". A Mudarabah's schedule is planned capital
 * returns, not a debt, so its messages never say instalment, due or owed, and they say how
 * capital is returned and who bears a genuine business loss (SchedulePresenter).
 */
class CampaignDisbursed extends Notification
{
    public const BORROWER = 'borrower';

    public const INVESTOR = 'investor';

    /**
     * @param  int  $firstCents  the first row's amount (the borrower), or the investor's share of it
     * @param  int|null  $pledgedCents  the investor's pledged total; null for the borrower
     */
    public function __construct(
        public readonly Campaign $campaign,
        public readonly string $audience,
        public readonly string $firstDateLabel,
        public readonly int $firstCents,
        public readonly ?int $pledgedCents = null,
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
            ? ['View your request', route('borrower.campaigns.show', $this->campaign)]
            : ['View the campaign', route('investor.campaigns.show', $this->campaign)];

        return $mail->line(FundingOutcome::SIMULATED)->action($label, $url);
    }

    /**
     * The message lines, in order (also what the tests read).
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $title = $this->campaign->title;
        $amount = Money::format(Money::fromDatabase($this->campaign->amount_requested));
        $n = (int) $this->campaign->tenure_months;
        $first = Money::format($this->firstCents);
        $date = $this->firstDateLabel;

        if ($this->audience === self::BORROWER) {
            return match ($this->campaign->contract_type) {
                Campaign::CONTRACT_CONVENTIONAL => [
                    "Your campaign \"{$title}\" reached its target, and {$amount} has been paid into your wallet.",
                    "You repay it in {$n} monthly instalments of capital and interest. The first, of {$first}, is due on {$date}.",
                ],
                Campaign::CONTRACT_MURABAHAH => [
                    "Your campaign \"{$title}\" reached its target, and {$amount} has been paid into your wallet to buy the asset.",
                    'You pay the sale price of '.Money::format(Money::fromDatabase($this->campaign->amount_requested) + Money::fromDatabase($this->campaign->markup_amount))
                        ." (the asset cost plus the fixed markup) in {$n} monthly instalments. The first, of {$first}, is due on {$date}. The markup is fixed and can't change.",
                ],
                Campaign::CONTRACT_MUDARABAH => [
                    "Your campaign \"{$title}\" reached its target, and {$amount} of investors' capital has been paid into your wallet.",
                    "The plan returns the capital in {$n} monthly planned capital returns. The first planned capital return is {$first}, on {$date}.",
                    SchedulePresenter::MUDARABAH_CAPITAL_NOTE,
                ],
            };
        }

        $pledged = Money::format((int) $this->pledgedCents);

        return match ($this->campaign->contract_type) {
            Campaign::CONTRACT_MUDARABAH => [
                "\"{$title}\" reached its target, and your capital of {$pledged} has been paid out to the business.",
                "The plan returns capital in {$n} monthly planned capital returns. Your share of the first, on {$date}, is {$first}.",
                SchedulePresenter::MUDARABAH_CAPITAL_NOTE,
            ],
            default => [
                "\"{$title}\" reached its target, and your pledge of {$pledged} has been paid out to the borrower.",
                "It is repaid in {$n} monthly instalments. Your share of the first, due on {$date}, is {$first}.",
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'audience' => $this->audience,
            'first_date' => $this->firstDateLabel,
            'first_amount' => Money::fromCents($this->firstCents),
            'pledged' => $this->pledgedCents === null ? null : Money::fromCents($this->pledgedCents),
            'message' => $this->subject(),
        ];
    }

    public function subject(): string
    {
        return $this->audience === self::BORROWER
            ? 'Your campaign is funded and the money is in your wallet'
            : 'A campaign you invested in is funded and paid out';
    }
}
