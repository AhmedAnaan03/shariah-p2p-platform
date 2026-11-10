<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-08: choose a new password from the emailed link.
 */
class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'Fresh#2026';

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'aisha@example.com', 'password' => 'Old#pass2026']);
        // The same token the emailed link would contain.
        $this->token = Password::createToken($this->user);
    }

    /**
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'token' => $this->token,
            'email' => 'aisha@example.com',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ], $overrides);
    }

    public function test_the_reset_page_renders_with_the_token_and_email_from_the_link(): void
    {
        $this->get("/reset-password/{$this->token}?email=aisha@example.com")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', $this->token)
                ->where('email', 'aisha@example.com')
            );
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'blank token' => [['token' => ''], 'token', 'This reset link is incomplete. Request a new one.'],
            'blank email' => [['email' => ''], 'email', 'Enter your email address.'],
            'email without @' => [['email' => 'aisha.example.com'], 'email', 'Enter a valid email address, like name@example.com.'],
            'blank password' => [['password' => '', 'password_confirmation' => ''], 'password', 'Enter a new password.'],
            'password under 8 characters' => [['password' => 'Ab1#', 'password_confirmation' => 'Ab1#'], 'password', 'Your password must be at least 8 characters.'],
            'password with no upper-case letter' => [['password' => 'fresh#2026', 'password_confirmation' => 'fresh#2026'], 'password', 'Your password must include both upper-case and lower-case letters.'],
            'password with no number' => [['password' => 'Fresh#pass', 'password_confirmation' => 'Fresh#pass'], 'password', 'Your password must include at least one number.'],
            'password with no symbol' => [['password' => 'Fresh2026', 'password_confirmation' => 'Fresh2026'], 'password', 'Your password must include at least one symbol, such as ! or #.'],
            'passwords do not match' => [['password_confirmation' => 'Other#2026'], 'password', 'The two passwords do not match.'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_a_clear_message(array $input, string $field, string $message): void
    {
        $this->from('/reset-password/x')->post('/reset-password', $this->validInput($input))
            ->assertRedirect('/reset-password/x')
            ->assertSessionHasErrors([$field => $message]);

        $this->assertTrue(Hash::check('Old#pass2026', $this->user->fresh()->password));
    }

    public function test_a_valid_reset_changes_the_password_and_goes_to_sign_in(): void
    {
        $this->post('/reset-password', $this->validInput())
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'Your password has been reset. Sign in with your new password.');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $this->user->fresh()->password));

        // And the new password actually works on the sign-in form.
        $this->post('/login', ['email' => 'aisha@example.com', 'password' => self::NEW_PASSWORD])
            ->assertRedirect('/borrower');
    }

    public function test_a_link_cannot_be_used_twice(): void
    {
        $this->post('/reset-password', $this->validInput());

        $this->from('/reset-password/x')
            ->post('/reset-password', $this->validInput(['password' => 'Again#2026', 'password_confirmation' => 'Again#2026']))
            ->assertSessionHasErrors(['email' => 'This reset link is invalid or has expired. Request a new one.']);
    }

    public function test_a_wrong_token_is_rejected(): void
    {
        $this->from('/reset-password/x')->post('/reset-password', $this->validInput(['token' => 'not-the-real-token']))
            ->assertSessionHasErrors(['email' => 'This reset link is invalid or has expired. Request a new one.']);

        $this->assertTrue(Hash::check('Old#pass2026', $this->user->fresh()->password));
    }

    public function test_a_token_used_with_another_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'other@example.com']);

        $this->from('/reset-password/x')->post('/reset-password', $this->validInput(['email' => 'other@example.com']))
            ->assertSessionHasErrors(['email' => 'This reset link is invalid or has expired. Request a new one.']);
    }

    public function test_a_link_older_than_60_minutes_has_expired(): void
    {
        $this->travel(61)->minutes();

        $this->from('/reset-password/x')->post('/reset-password', $this->validInput())
            ->assertSessionHasErrors(['email' => 'This reset link is invalid or has expired. Request a new one.']);

        $this->assertTrue(Hash::check('Old#pass2026', $this->user->fresh()->password));
    }

    public function test_a_link_still_works_just_before_60_minutes(): void
    {
        $this->travel(59)->minutes();

        $this->post('/reset-password', $this->validInput())->assertRedirect('/login');
    }
}
