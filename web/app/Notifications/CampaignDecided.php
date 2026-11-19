<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\User;
use App\Services\CampaignReviewService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a borrower that an administrator approved, rejected or suspended their financing
 * campaign (PB-15). Stored in the notifications table (PB-39 shows it in-app) and emailed.
 * Like VerificationDecided, it is sent straight away rather than queued.
 *
 * The wording is the same for every contract and never says "interest" or "guaranteed", so it
 * is safe for Shariah requests.
 */
class CampaignDecided extends Notification
{
    public function __construct(
        public readonly Campaign $campaign,
        public readonly string $decision,
        public readonly ?string $reason = null,
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
        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting("Hello {$notifiable->name},");

        $mail = match ($this->decision) {
            CampaignReviewService::APPROVE => $mail
                ->line("Your financing request \"{$title}\" has been approved and is now open to investors.")
                ->line('It is open to investors until the end of '.$this->campaign->fundingDeadlineLabel().'.'),
            CampaignReviewService::REJECT => $mail
                ->line("An administrator reviewed your financing request \"{$title}\" and could not approve it.")
                ->line("Reason: {$this->reason}")
                ->line('You can submit a new request once you have addressed this.'),
            CampaignReviewService::SUSPEND => $mail
                ->line("Your campaign \"{$title}\" has been suspended, so investors can no longer see it or pledge to it.")
                ->line("Reason: {$this->reason}"),
        };

        return $mail->action('View your request', route('borrower.campaigns.show', $this->campaign));
    }

    /**
     * What is stored in notifications.data for the in-app notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'decision' => $this->decision,
            'reason' => $this->reason,
            'message' => $this->subject(),
        ];
    }

    private function subject(): string
    {
        return match ($this->decision) {
            CampaignReviewService::APPROVE => 'Your financing request is live',
            CampaignReviewService::REJECT => 'Your financing request was not approved',
            CampaignReviewService::SUSPEND => 'Your campaign has been suspended',
        };
    }
}
