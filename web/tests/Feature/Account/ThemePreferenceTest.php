<?php

namespace Tests\Feature\Account;

use App\Models\InvestorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-55 and PB-57: the light / dark switch. The choice is saved per user together with the
 * device's setting at that moment; with no choice the interface follows the device; the server
 * writes both on <html> so the head script can apply "the most recent change wins" before the
 * first paint; and the theme combines with the Shariah mode. (The rule itself is tested in
 * resources/js/lib/theme.test.js; the real round trip in the browser by `npm run screenshots`.)
 */
class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_has_no_choice_so_the_device_decides(): void
    {
        $user = User::factory()->borrower()->create();

        $this->assertNull($user->fresh()->theme);
        $this->assertNull($user->fresh()->theme_system_at_choice);
        $this->actingAs($user)->get('/borrower')
            ->assertInertia(fn (Assert $page) => $page->where('theme', ['choice' => null, 'systemAtChoice' => null]));
    }

    /**
     * [chosen theme, device setting at the time]
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function choices(): array
    {
        return [
            'dark on a light device' => ['dark', 'light'],
            'light on a dark device' => ['light', 'dark'],
            'dark on a dark device' => ['dark', 'dark'],
            'light on a light device' => ['light', 'light'],
        ];
    }

    #[DataProvider('choices')]
    public function test_the_choice_is_saved_with_the_device_setting_at_the_time(string $theme, string $system): void
    {
        $user = User::factory()->investor()->create();

        $this->actingAs($user)->from('/investor')->patch('/account/theme', ['theme' => $theme, 'system' => $system])
            ->assertRedirect('/investor')
            ->assertSessionHasNoErrors();

        $this->assertSame($theme, $user->fresh()->theme);
        $this->assertSame($system, $user->fresh()->theme_system_at_choice);
    }

    /**
     * Blank and invalid values: [input, field, message].
     *
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidChoices(): array
    {
        $theme = 'Choose light or dark.';
        $system = 'Send the device\'s own setting, light or dark.';

        return [
            'nothing sent' => [[], 'theme', $theme],
            'blank theme' => [['theme' => '', 'system' => 'light'], 'theme', $theme],
            "'system' is no longer a choice" => [['theme' => 'system', 'system' => 'light'], 'theme', $theme],
            'unknown theme' => [['theme' => 'purple', 'system' => 'light'], 'theme', $theme],
            'wrong case' => [['theme' => 'Dark', 'system' => 'light'], 'theme', $theme],
            'theme as an array' => [['theme' => ['dark'], 'system' => 'light'], 'theme', $theme],
            'device setting missing' => [['theme' => 'dark'], 'system', $system],
            'device setting unknown' => [['theme' => 'dark', 'system' => 'sepia'], 'system', $system],
        ];
    }

    #[DataProvider('invalidChoices')]
    public function test_an_invalid_choice_is_refused_and_nothing_changes(array $input, string $field, string $message): void
    {
        $user = User::factory()->borrower()->create();

        $this->actingAs($user)->from('/borrower')->patch('/account/theme', $input)
            ->assertSessionHasErrors([$field => $message]);

        $this->assertNull($user->fresh()->theme);
    }

    public function test_every_role_can_save_its_own_theme_and_only_its_own(): void
    {
        foreach (['borrower', 'investor', 'admin'] as $role) {
            $user = User::factory()->{$role}()->create();
            $other = User::factory()->borrower()->create();

            $this->actingAs($user)->patch('/account/theme', ['theme' => 'dark', 'system' => 'light', 'user_id' => $other->id])
                ->assertSessionHasNoErrors();

            $this->assertSame('dark', $user->fresh()->theme, "{$role} saves their theme");
            $this->assertNull($other->fresh()->theme, 'No one else is changed');
        }
    }

    public function test_guests_cannot_save_a_theme(): void
    {
        $this->patch('/account/theme', ['theme' => 'dark', 'system' => 'light'])->assertRedirect('/login');
    }

    public function test_the_theme_cannot_be_set_through_registration(): void
    {
        $this->post('/register', [
            'name' => 'Aishath Rasheed', 'email' => 'aishath@example.com', 'role' => 'investor',
            'password' => 'Correct-Horse-9', 'password_confirmation' => 'Correct-Horse-9',
            'theme' => 'dark', 'theme_system_at_choice' => 'dark',
        ]);

        $user = User::where('email', 'aishath@example.com')->sole();
        $this->assertNull($user->theme);
        $this->assertNull($user->theme_system_at_choice);
    }

    /**
     * What the server writes on <html>: [saved choice, device at choice, expected attributes].
     *
     * @return array<string, array{0: ?string, 1: ?string, 2: string}>
     */
    public static function firstPaint(): array
    {
        return [
            'no choice' => [null, null, 'data-theme="light" data-theme-choice="" data-theme-system-at-choice=""'],
            'dark chosen on a light device' => ['dark', 'light', 'data-theme="dark" data-theme-choice="dark" data-theme-system-at-choice="light"'],
            'light chosen on a dark device' => ['light', 'dark', 'data-theme="light" data-theme-choice="light" data-theme-system-at-choice="dark"'],
        ];
    }

    #[DataProvider('firstPaint')]
    public function test_the_choice_and_device_setting_are_in_the_servers_html_for_the_first_paint(?string $choice, ?string $system, string $attributes): void
    {
        $user = User::factory()->borrower()->create();
        $user->forceFill(['theme' => $choice, 'theme_system_at_choice' => $system])->save();

        $html = $this->actingAs($user)->get('/borrower')->assertOk()->getContent();

        $this->assertStringContainsString($attributes, $html);
        // The head script that applies the rule runs before the stylesheet loads.
        $this->assertStringContainsString('d.themeSystemAtChoice === device) ? d.themeChoice : device', $html);
        $this->assertLessThan(strpos($html, '<link'), strpos($html, 'prefers-color-scheme'));
    }

    public function test_a_guest_page_follows_the_device(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-theme-choice=""', false);
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
        $profile->user->forceFill(['theme' => $theme, 'theme_system_at_choice' => $theme])->save();

        $this->actingAs($profile->user)->get('/investor')
            ->assertOk()
            ->assertSee("data-mode=\"{$mode}\" data-theme=\"{$dataTheme}\"", false)
            ->assertInertia(fn (Assert $page) => $page->where('mode', $mode)->where('theme.choice', $theme));
    }
}
