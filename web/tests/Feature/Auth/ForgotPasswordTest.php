<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-08: request a password reset link.
 */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_page_renders(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidEmails(): array
    {
        return [
            'blank email' => ['', 'Enter your email address.'],
            'email without @' => ['aisha.example.com', 'Enter a valid email address, like name@example.com.'],
        ];
    }

    #[DataProvider('invalidEmails')]
    public function test_an_invalid_email_is_rejected_with_a_clear_message(string $email, string $message): void
    {
        Notification::fake();

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['email' => $message]);

        Notification::assertNothingSent();
    }

    public function test_a_reset_link_is_sent_to_a_registered_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'aisha@example.com']);

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'aisha@example.com'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', ForgotPasswordController::SENT_MESSAGE);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            // The link in the email opens our reset page for this user.
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, '/reset-password/'.$notification->token)
                && str_contains($url, 'email='.urlencode('aisha@example.com'));
        });
    }

    public function test_an_unregistered_email_gets_the_same_answer_and_no_email(): void
    {
        Notification::fake();

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', ForgotPasswordController::SENT_MESSAGE);

        Notification::assertNothingSent();
    }

    public function test_with_the_log_mailer_the_reset_email_is_written_to_the_log(): void
    {
        // Same set-up as development (MAIL_MAILER=log), but logging to a file of its own.
        $logFile = storage_path('logs/test-reset-mail.log');
        File::delete($logFile);
        config([
            'mail.default' => 'log',
            'mail.mailers.log.channel' => 'reset_mail_test',
            'logging.channels.reset_mail_test' => ['driver' => 'single', 'path' => $logFile],
        ]);
        User::factory()->create(['email' => 'aisha@example.com']);

        $this->post('/forgot-password', ['email' => 'aisha@example.com']);

        $logged = File::get($logFile);
        File::delete($logFile);
        $this->assertStringContainsString('To: aisha@example.com', $logged);
        $this->assertStringContainsString('/reset-password/', $logged);
    }

    public function test_development_is_configured_to_use_the_log_mailer(): void
    {
        $this->assertStringContainsString("MAIL_MAILER=log\n", File::get(base_path('.env.example')));
    }

    public function test_reset_links_expire_after_60_minutes(): void
    {
        $this->assertSame(60, config('auth.passwords.users.expire'));
    }

    public function test_more_than_six_requests_a_minute_are_refused(): void
    {
        Notification::fake();

        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => "user{$i}@example.com"])->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => 'user7@example.com'])->assertTooManyRequests();
    }
}
