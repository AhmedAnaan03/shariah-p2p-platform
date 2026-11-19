<?php

namespace Tests\Feature\Account;

use App\Models\InvestorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-55: the light / dark / system theme is saved per user, defaults to the device's setting,
 * is written into the server's HTML for the first paint, and combines with the Shariah mode.
 */
class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_follows_the_system_setting(): void
    {
        $user = User::factory()->borrower()->create();

        $this->assertSame('system', $user->fresh()->theme);
        $this->actingAs($user)->get('/borrower')->assertInertia(fn (Assert $page) => $page->where('theme', 'system'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function themes(): array
    {
        return ['light' => ['light'], 'dark' => ['dark'], 'system' => ['system']];
    }

    #[DataProvider('themes')]
    public function test_a_valid_choice_is_saved_for_the_user(string $theme): void
    {
        $user = User::factory()->investor()->create();

        $this->actingAs($user)->from('/investor')->patch('/account/theme', ['theme' => $theme])
            ->assertRedirect('/investor')
            ->assertSessionHasNoErrors();

        $this->assertSame($theme, $user->fresh()->theme);
    }

    /**
     * Blank and invalid values: [input].
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidChoices(): array
    {
        return [
            'missing' => [[]],
            'blank' => [['theme' => '']],
            'unknown theme' => [['theme' => 'purple']],
            'wrong case' => [['theme' => 'Dark']],
            'an array' => [['theme' => ['dark']]],
        ];
    }

    #[DataProvider('invalidChoices')]
    public function test_an_invalid_choice_is_refused_and_nothing_changes(array $input): void
    {
        $user = User::factory()->borrower()->create();

        $this->actingAs($user)->from('/borrower')->patch('/account/theme', $input)
            ->assertSessionHasErrors(['theme' => 'Choose light, dark or system.']);

        $this->assertSame('system', $user->fresh()->theme);
    }

    public function test_every_role_can_save_its_own_theme_and_only_its_own(): void
    {
        foreach (['borrower', 'investor', 'admin'] as $role) {
            $user = User::factory()->{$role}()->create();
            $other = User::factory()->borrower()->create();

            $this->actingAs($user)->patch('/account/theme', ['theme' => 'dark', 'user_id' => $other->id])->assertSessionHasNoErrors();

            $this->assertSame('dark', $user->fresh()->theme, "{$role} saves their theme");
            $this->assertSame('system', $other->fresh()->theme, 'No one else is changed');
        }
    }

    public function test_guests_cannot_save_a_theme(): void
    {
        $this->patch('/account/theme', ['theme' => 'dark'])->assertRedirect('/login');
    }

    public function test_the_theme_cannot_be_set_through_registration(): void
    {
        $this->post('/register', [
            'name' => 'Aishath Rasheed', 'email' => 'aishath@example.com', 'role' => 'investor',
            'password' => 'Correct-Horse-9', 'password_confirmation' => 'Correct-Horse-9', 'theme' => 'dark',
        ]);

        $this->assertSame('system', User::where('email', 'aishath@example.com')->value('theme'));
    }

    /**
     * What the server writes on <html> for the first paint: [saved theme, data-theme, data-theme-preference].
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function firstPaint(): array
    {
        return [
            'light' => ['light', 'light', 'light'],
            'dark' => ['dark', 'dark', 'dark'],
            // The device decides; the inline script in <head> sets data-theme before painting.
            'system' => ['system', 'light', 'system'],
        ];
    }

    #[DataProvider('firstPaint')]
    public function test_the_saved_theme_is_in_the_servers_html_so_there_is_no_flash(string $saved, string $dataTheme, string $preference): void
    {
        $user = User::factory()->borrower()->create();
        $user->forceFill(['theme' => $saved])->save();

        $html = $this->actingAs($user)->get('/borrower')->assertOk()->getContent();

        $this->assertStringContainsString("data-theme=\"{$dataTheme}\" data-theme-preference=\"{$preference}\"", $html);
        $this->assertStringContainsString("matchMedia('(prefers-color-scheme: dark)')", $html, 'The no-flash script is in <head>.');
        $this->assertLessThan(strpos($html, '<link'), strpos($html, 'prefers-color-scheme'), 'It runs before the stylesheet loads.');
    }

    public function test_a_guest_page_follows_the_device(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-theme-preference="system"', false);
    }

    /**
     * All four palettes: [Shariah-only, theme, data-mode, data-theme].
     *
     * @return array<string, array{0: bool, 1: string, 2: string, 3: string}>
     */
    public static function fourPalettes(): array
    {
        return [
            'conventional light' => [false, 'light', 'conventional', 'light'],
            'conventional dark' => [false, 'dark', 'conventional', 'dark'],
            'Shariah light' => [true, 'light', 'shariah', 'light'],
            'Shariah dark' => [true, 'dark', 'shariah', 'dark'],
        ];
    }

    #[DataProvider('fourPalettes')]
    public function test_the_theme_combines_with_the_shariah_mode(bool $shariahOnly, string $theme, string $mode, string $dataTheme): void
    {
        $profile = InvestorProfile::factory()->create(['shariah_only' => $shariahOnly]);
        $profile->user->forceFill(['theme' => $theme])->save();

        $this->actingAs($profile->user)->get('/investor')
            ->assertOk()
            ->assertSee("data-mode=\"{$mode}\" data-theme=\"{$dataTheme}\"", false)
            ->assertInertia(fn (Assert $page) => $page->where('mode', $mode)->where('theme', $theme));
    }
}
