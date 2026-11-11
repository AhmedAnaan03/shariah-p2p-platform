<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-08: change password while signed in.
 */
class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT = 'Current#2026';

    private const NEW_PASSWORD = 'Fresh#2026';

    private function user(string $role = 'borrower'): User
    {
        return User::factory()->{$role}()->create(['password' => self::CURRENT]);
    }

    /**
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'current_password' => self::CURRENT,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ], $overrides);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return ['borrower' => ['borrower'], 'investor' => ['investor'], 'admin' => ['admin']];
    }

    #[DataProvider('roles')]
    public function test_every_role_can_open_the_change_password_page(string $role): void
    {
        $this->actingAs($this->user($role))->get('/account/password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Account/ChangePassword'));
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'blank current password' => [['current_password' => ''], 'current_password', 'Enter your current password.'],
            'wrong current password' => [['current_password' => 'Wrong#2026'], 'current_password', 'Your current password is incorrect.'],
            'blank new password' => [['password' => '', 'password_confirmation' => ''], 'password', 'Enter a new password.'],
            'new password under 8 characters' => [['password' => 'Ab1#', 'password_confirmation' => 'Ab1#'], 'password', 'Your password must be at least 8 characters.'],
            'new password with no upper-case letter' => [['password' => 'fresh#2026', 'password_confirmation' => 'fresh#2026'], 'password', 'Your password must include both upper-case and lower-case letters.'],
            'new password with no number' => [['password' => 'Fresh#pass', 'password_confirmation' => 'Fresh#pass'], 'password', 'Your password must include at least one number.'],
            'new password with no symbol' => [['password' => 'Fresh2026', 'password_confirmation' => 'Fresh2026'], 'password', 'Your password must include at least one symbol, such as ! or #.'],
            'new passwords do not match' => [['password_confirmation' => 'Other#2026'], 'password', 'The two new passwords do not match.'],
            'new password same as current' => [['password' => self::CURRENT, 'password_confirmation' => self::CURRENT], 'password', 'Your new password must be different from your current one.'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_a_clear_message(array $input, string $field, string $message): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/account/password')->put('/account/password', $this->validInput($input))
            ->assertRedirect('/account/password')
            ->assertSessionHasErrors([$field => $message]);

        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }

    public function test_a_valid_change_saves_the_new_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/account/password')->put('/account/password', $this->validInput())
            ->assertRedirect('/account/password')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your password has been changed.');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_a_guest_cannot_change_a_password(): void
    {
        $this->put('/account/password', $this->validInput())->assertRedirect('/login');
    }
}
