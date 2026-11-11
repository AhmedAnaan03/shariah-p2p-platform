<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-06: register an account with a role.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A registration that passes every rule. Each test changes only what it is about.
     *
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aisha Ibrahim',
            'email' => 'aisha@example.com',
            'password' => 'Secure#2026',
            'password_confirmation' => 'Secure#2026',
            'role' => Role::BORROWER,
        ], $overrides);
    }

    public function test_the_registration_page_offers_only_borrower_and_investor(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Register')
                ->has('roles', 2)
                ->where('roles.0.name', Role::BORROWER)
                ->where('roles.1.name', Role::INVESTOR)
            );
    }

    /**
     * Blank and invalid inputs: [changed input, field with the error, expected message].
     *
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'blank name' => [['name' => ''], 'name', 'Enter your full name.'],
            'name over 255 characters' => [['name' => str_repeat('a', 256)], 'name', 'Your name must be 255 characters or fewer.'],
            'blank email' => [['email' => ''], 'email', 'Enter your email address.'],
            'email without @' => [['email' => 'aisha.example.com'], 'email', 'Enter a valid email address, like name@example.com.'],
            'blank password' => [['password' => '', 'password_confirmation' => ''], 'password', 'Enter a password.'],
            'password under 8 characters' => [['password' => 'Ab1#', 'password_confirmation' => 'Ab1#'], 'password', 'Your password must be at least 8 characters.'],
            'password with no upper-case letter' => [['password' => 'secure#2026', 'password_confirmation' => 'secure#2026'], 'password', 'Your password must include both upper-case and lower-case letters.'],
            'password with no number' => [['password' => 'Secure#pass', 'password_confirmation' => 'Secure#pass'], 'password', 'Your password must include at least one number.'],
            'password with no symbol' => [['password' => 'Secure2026', 'password_confirmation' => 'Secure2026'], 'password', 'Your password must include at least one symbol, such as ! or #.'],
            'passwords do not match' => [['password_confirmation' => 'Different#2026'], 'password', 'The two passwords do not match.'],
            'blank role' => [['role' => ''], 'role', 'Choose whether you are registering as a borrower or an investor.'],
            'role admin' => [['role' => Role::ADMIN], 'role', 'Choose whether you are registering as a borrower or an investor.'],
            'role that does not exist' => [['role' => 'superuser'], 'role', 'Choose whether you are registering as a borrower or an investor.'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_a_clear_message(array $input, string $field, string $message): void
    {
        $response = $this->from('/register')->post('/register', $this->validInput($input));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([$field => $message]);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_email_that_is_already_registered_is_rejected(): void
    {
        User::factory()->create(['email' => 'aisha@example.com']);

        $response = $this->from('/register')->post('/register', $this->validInput());

        $response->assertSessionHasErrors(['email' => 'An account with this email address already exists. Sign in instead.']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_the_same_email_in_different_case_is_rejected_as_a_duplicate(): void
    {
        User::factory()->create(['email' => 'aisha@example.com']);

        $response = $this->from('/register')->post('/register', $this->validInput(['email' => 'Aisha@Example.COM']));

        $response->assertSessionHasErrors(['email' => 'An account with this email address already exists. Sign in instead.']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_borrower_registers_and_lands_on_the_borrower_portal(): void
    {
        $response = $this->post('/register', $this->validInput());

        $response->assertRedirect('/borrower');
        $response->assertSessionHas('status', 'Your account has been created.');

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Aisha Ibrahim', $user->name);
        $this->assertSame('aisha@example.com', $user->email);
        $this->assertTrue($user->hasRole(Role::BORROWER));
    }

    public function test_an_investor_registers_and_lands_on_the_investor_portal(): void
    {
        $response = $this->post('/register', $this->validInput(['role' => Role::INVESTOR]));

        $response->assertRedirect('/investor');
        $this->assertTrue(User::sole()->hasRole(Role::INVESTOR));
    }

    public function test_a_new_account_starts_pending_verification(): void
    {
        $this->post('/register', $this->validInput());

        $this->assertDatabaseHas('users', [
            'email' => 'aisha@example.com',
            'status' => 'pending_verification',
        ]);
    }

    public function test_the_portal_shows_the_pending_status_after_registering(): void
    {
        $this->post('/register', $this->validInput());

        // PortalLayout shows the pending-verification notice when auth.user.status is pending.
        $this->get('/borrower')->assertInertia(fn (Assert $page) => $page
            ->component('Borrower/Portal')
            ->where('auth.user.status', 'pending_verification')
            ->where('flash.status', 'Your account has been created.')
        );
    }

    public function test_the_password_is_stored_hashed_not_as_typed(): void
    {
        $this->post('/register', $this->validInput());

        $stored = User::sole()->password;
        $this->assertNotSame('Secure#2026', $stored);
        $this->assertTrue(Hash::check('Secure#2026', $stored));
    }

    public function test_the_email_is_stored_in_lower_case(): void
    {
        $this->post('/register', $this->validInput(['email' => 'Aisha@Example.COM']));

        $this->assertSame('aisha@example.com', User::sole()->email);
    }

    public function test_extra_fields_cannot_make_the_account_verified_or_admin(): void
    {
        $this->post('/register', $this->validInput([
            'status' => 'verified',
            'role_id' => (string) Role::idFor(Role::ADMIN),
        ]));

        $user = User::sole();
        $this->assertSame('pending_verification', $user->status);
        $this->assertTrue($user->hasRole(Role::BORROWER));
    }

    public function test_a_signed_in_user_opening_register_is_sent_to_their_portal(): void
    {
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->get('/register')->assertRedirect('/investor');
    }
}
