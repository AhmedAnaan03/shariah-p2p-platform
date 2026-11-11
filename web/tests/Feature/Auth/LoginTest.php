<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-07: sign in, rate limiting and sign out.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secure#2026';

    private function borrower(): User
    {
        return User::factory()->borrower()->create([
            'email' => 'aisha@example.com',
            'password' => self::PASSWORD,
        ]);
    }

    public function test_the_sign_in_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'blank email' => [['email' => '', 'password' => self::PASSWORD], 'email', 'Enter your email address.'],
            'email without @' => [['email' => 'aisha.example.com', 'password' => self::PASSWORD], 'email', 'Enter a valid email address, like name@example.com.'],
            'blank password' => [['email' => 'aisha@example.com', 'password' => ''], 'password', 'Enter your password.'],
            'wrong password' => [['email' => 'aisha@example.com', 'password' => 'Wrong#2026'], 'email', 'These details do not match our records.'],
            'email with no account' => [['email' => 'nobody@example.com', 'password' => self::PASSWORD], 'email', 'These details do not match our records.'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_sign_in_is_rejected_with_a_clear_message(array $input, string $field, string $message): void
    {
        $this->borrower();

        $response = $this->from('/login')->post('/login', $input);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([$field => $message]);
        $this->assertGuest();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function roles(): array
    {
        return [
            'borrower' => ['borrower', '/borrower'],
            'investor' => ['investor', '/investor'],
            'admin' => ['admin', '/admin'],
        ];
    }

    #[DataProvider('roles')]
    public function test_signing_in_redirects_to_the_users_own_portal(string $role, string $portal): void
    {
        $user = User::factory()->{$role}()->create(['password' => self::PASSWORD]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        $response->assertRedirect($portal);
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('roles')]
    public function test_the_seeded_demo_account_signs_in_to_its_portal(string $role, string $portal): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/login', ['email' => "{$role}@example.com", 'password' => DatabaseSeeder::DEMO_PASSWORD])
            ->assertRedirect($portal);
    }

    public function test_a_pending_account_can_still_sign_in(): void
    {
        // PB-09/PB-10: pending users must sign in to complete the profile that gets verified.
        $user = $this->borrower();
        $this->assertTrue($user->isPending());

        $this->post('/login', ['email' => 'aisha@example.com', 'password' => self::PASSWORD])
            ->assertRedirect('/borrower');
    }

    public function test_after_five_failed_attempts_sign_in_is_locked_even_with_the_right_password(): void
    {
        Event::fake([Lockout::class]);
        $this->borrower();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->post('/login', ['email' => 'aisha@example.com', 'password' => 'Wrong#2026']);
        }

        $response = $this->from('/login')->post('/login', ['email' => 'aisha@example.com', 'password' => self::PASSWORD]);

        $response->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Too many sign-in attempts. Try again in', session('errors')->first('email'));
        $this->assertGuest();
        Event::assertDispatched(Lockout::class);
    }

    public function test_four_failed_attempts_do_not_lock_the_account(): void
    {
        $this->borrower();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS - 1; $i++) {
            $this->post('/login', ['email' => 'aisha@example.com', 'password' => 'Wrong#2026']);
        }

        $this->post('/login', ['email' => 'aisha@example.com', 'password' => self::PASSWORD])
            ->assertRedirect('/borrower');
    }

    public function test_the_lock_lifts_after_the_decay_time(): void
    {
        $this->borrower();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->post('/login', ['email' => 'aisha@example.com', 'password' => 'Wrong#2026']);
        }

        $this->travel(LoginRequest::DECAY_SECONDS + 1)->seconds();

        $this->post('/login', ['email' => 'aisha@example.com', 'password' => self::PASSWORD])
            ->assertRedirect('/borrower');
    }

    public function test_the_lock_is_per_account_so_other_users_can_still_sign_in(): void
    {
        $this->borrower();
        $other = User::factory()->investor()->create(['password' => self::PASSWORD]);

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->post('/login', ['email' => 'aisha@example.com', 'password' => 'Wrong#2026']);
        }

        $this->post('/login', ['email' => $other->email, 'password' => self::PASSWORD])
            ->assertRedirect('/investor');
    }

    public function test_signing_out_ends_the_session(): void
    {
        $user = $this->borrower();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        // The portal now needs a fresh sign-in.
        $this->get('/borrower')->assertRedirect('/login');
    }

    public function test_sign_out_must_be_a_post_request(): void
    {
        $this->actingAs($this->borrower())->get('/logout')->assertMethodNotAllowed();
        $this->assertAuthenticated();
    }

    public function test_a_signed_in_user_opening_sign_in_is_sent_to_their_portal(): void
    {
        $this->actingAs($this->borrower())->get('/login')->assertRedirect('/borrower');
    }
}
