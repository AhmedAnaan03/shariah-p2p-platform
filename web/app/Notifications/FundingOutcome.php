<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\User;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A campaign that wasn't funded (PB-20), for the borrower or one investor. Stored in the
 * notifications table and emailed, straight away, like CampaignDecided. A funded campaign is
 * announced by CampaignDisbursed instead, once it has been paid out (PB-34).
 *
 * - FAILED_BORROWER: the deadline passed short of the target; every pledge was refunded.
 * - REFUNDED_INVESTOR: the investor's pledge(s) to that campaign came back to their wallet.
 *
 * The same wording for every contract, never "interest" or "guaranteed", so it is safe for
 * Shariah campaigns. Every message says the money is simulated.
 */
class FundingOutcome extends Notification
{
    public const FAILED_BORROWER = 'failed_borrower';

    public const REFUNDED_INVESTOR = 'refunded_investor';

    public const SIMULATED = 'Simulated wallet, no real money.';

    /**
     * @param  int|null  $cents  the investor's own refunded total
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
            self::FAILED_BORROWER => $mail
                ->line("Your campaign \"{$title}\" closed before reaching its target, so it was not funded.")
                ->line('Every pledge has been returned to the investors. You can submit a new request.'),
            self::REFUNDED_INVESTOR => $mail
                ->line("\"{$title}\" closed before reaching its target, so it was not funded.")
                ->line('Your pledge of '.Money::format((int) $this->cents).' has been returned to your wallet.'),
        };

        [$label, $url] = match ($this->outcome) {
            self::FAILED_BORROWER => ['View your request', route('borrower.campaigns.show', $this->campaign)],
            self::REFUNDED_INVESTOR => ['View your wallet', route('investor.wallet.show')],
        };

        return $mail->line(self::SIMULATED)->action($label, $url);
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
            self::FAILED_BORROWER => 'Your campaign was not funded',
            self::REFUNDED_INVESTOR => 'Your pledge has been refunded',
        };
    }
}
