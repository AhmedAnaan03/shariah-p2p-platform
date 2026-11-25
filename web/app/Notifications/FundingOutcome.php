<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\User;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * How a campaign's funding ended (PB-20), for the borrower or one investor. Stored in the
 * notifications table and emailed, straight away, like CampaignDecided.
 *
 * - FUNDED_BORROWER / FUNDED_INVESTOR: the target was reached (all or nothing: funded).
 * - FAILED_BORROWER: the deadline passed short of the target; every pledge was refunded.
 * - REFUNDED_INVESTOR: the investor's pledge(s) to that campaign came back to their wallet.
 *
 * The same wording for every contract, never "interest" or "guaranteed", so it is safe for
 * Shariah campaigns. Every message says the money is simulated.
 */
class FundingOutcome extends Notification
{
    public const FUNDED_BORROWER = 'funded_borrower';

    public const FUNDED_INVESTOR = 'funded_investor';

    public const FAILED_BORROWER = 'failed_borrower';

    public const REFUNDED_INVESTOR = 'refunded_investor';

    public const SIMULATED = 'Simulated wallet, no real money.';

    /**
     * @param  int|null  $cents  the investor's own pledged or refunded total
     */
    public function __construct(
        public readonly Campaign $campaign,
        public readonly string $outcome,
        public readonly ?int $cents = null,
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
        $title = $this->campaign->title;
        $mail = (new MailMessage)->subject($this->subject())->greeting("Hello {$notifiable->name},");

        $mail = match ($this->outcome) {
            self::FUNDED_BORROWER => $mail
                ->line("Your campaign \"{$title}\" reached its target of ".Money::format(Money::fromDatabase($this->campaign->amount_requested)).'.')
                ->line('The money is held in escrow until it is paid out to you.'),
            self::FUNDED_INVESTOR => $mail
                ->line("\"{$title}\" reached its target, so your pledge of ".Money::format((int) $this->cents).' goes ahead.')
                ->line('It stays in escrow until it is paid out to the borrower.'),
            self::FAILED_BORROWER => $mail
                ->line("Your campaign \"{$title}\" closed before reaching its target, so it was not funded.")
                ->line('Every pledge has been returned to the investors. You can submit a new request.'),
            self::REFUNDED_INVESTOR => $mail
                ->line("\"{$title}\" closed before reaching its target, so it was not funded.")
                ->line('Your pledge of '.Money::format((int) $this->cents).' has been returned to your wallet.'),
        };

        $url = in_array($this->outcome, [self::FUNDED_BORROWER, self::FAILED_BORROWER], true)
            ? route('borrower.campaigns.show', $this->campaign)
            : route('investor.wallet.show');

        return $mail->line(self::SIMULATED)->action($url === route('investor.wallet.show') ? 'View your wallet' : 'View your request', $url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'outcome' => $this->outcome,
            'amount' => $this->cents === null ? null : Money::fromCents($this->cents),
            'message' => $this->subject(),
        ];
    }

    public function subject(): string
    {
        return match ($this->outcome) {
            self::FUNDED_BORROWER => 'Your campaign is fully funded',
            self::FUNDED_INVESTOR => 'A campaign you pledged to is fully funded',
            self::FAILED_BORROWER => 'Your campaign was not funded',
            self::REFUNDED_INVESTOR => 'Your pledge has been refunded',
        };
    }
}
