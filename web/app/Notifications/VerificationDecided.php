<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a borrower or investor that an administrator approved or rejected their verification
 * (PB-11). Stored in the notifications table (shown in-app by PB-39's notification centre)
 * and emailed; in development MAIL_MAILER=log writes the email to storage/logs/laravel.log.
 *
 * Sent straight away rather than queued, so it works without a queue worker running.
 */
class VerificationDecided extends Notification
{
    public function __construct(
        public readonly bool $approved,
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
        $mail = (new MailMessage)->greeting("Hello {$notifiable->name},");

        if ($this->approved) {
            return $mail
                ->subject('Your account has been verified')
                ->line('An administrator has checked your profile and documents and verified your account.')
                ->action('Go to your portal', route($notifiable->portalRoute()));
        }

        return $mail
            ->subject('Your account verification was not approved')
            ->line('An administrator reviewed your profile and documents but could not verify your account.')
            ->line("Reason: {$this->reason}")
            ->line('Update your profile or upload a new document and your account will be reviewed again.')
            ->action('Go to your portal', route($notifiable->portalRoute()));
    }

    /**
     * What is stored in notifications.data for the in-app notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'decision' => $this->approved ? 'approved' : 'rejected',
            'reason' => $this->reason,
            'message' => $this->approved
                ? 'Your account has been verified.'
                : 'Your account verification was not approved.',
        ];
    }
}
