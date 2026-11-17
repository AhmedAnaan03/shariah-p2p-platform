<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\InvestorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PB-51: every page gets the platform mode as the shared `mode` prop, and the first HTML
 * response already carries it on <html data-mode>, so there is no flash of the wrong palette.
 */
class PlatformModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_the_conventional_mode(): void
    {
        $this->get('/login')
            ->assertSee('data-mode="conventional"', false)
            ->assertSee('data-theme="light"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('mode', 'conventional')
                ->where('modeSwitch', null)
            );
    }

    public function test_a_shariah_only_investor_gets_the_shariah_mode_on_first_paint(): void
    {
        $profile = InvestorProfile::factory()->create(['shariah_only' => true]);

        $this->actingAs($profile->user)->get('/investor')
            ->assertSee('data-mode="shariah"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('mode', 'shariah')
                ->where('modeSwitch.available', true)
                ->where('modeSwitch.url', route('investor.preferences.shariah-only'))
            );
    }

    public function test_an_investor_who_has_not_chosen_shariah_only_is_conventional(): void
    {
        $profile = InvestorProfile::factory()->create(['shariah_only' => false]);

        $this->actingAs($profile->user)->get('/investor')
            ->assertSee('data-mode="conventional"', false)
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'conventional'));
    }

    public function test_an_investor_without_a_profile_gets_a_disabled_switch(): void
    {
        $this->actingAs(User::factory()->investor()->create())->get('/investor')
            ->assertInertia(fn (Assert $page) => $page
                ->where('mode', 'conventional')
                ->where('modeSwitch.available', false)
            );
    }

    public function test_borrowers_and_admins_are_conventional_and_get_no_switch(): void
    {
        foreach (['borrower', 'admin'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())->get("/{$role}")
                ->assertSee('data-mode="conventional"', false)
                ->assertInertia(fn (Assert $page) => $page
                    ->where('mode', 'conventional')
                    ->where('modeSwitch', null)
                );
        }
    }

    public function test_a_borrower_is_conventional_until_they_make_a_shariah_request(): void
    {
        $borrower = User::factory()->borrower()->verified()->create();

        $this->actingAs($borrower)->get('/borrower')
            ->assertSee('data-mode="conventional"', false)
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'conventional'));

        Campaign::factory()->murabahah()->for($borrower, 'borrower')->create();

        $this->actingAs($borrower)->get('/borrower')
            ->assertSee('data-mode="shariah"', false)
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'shariah'));
    }

    public function test_a_borrowers_mode_follows_their_most_recent_request(): void
    {
        $borrower = User::factory()->borrower()->verified()->create();
        Campaign::factory()->mudarabah()->for($borrower, 'borrower')->create();
        Campaign::factory()->conventional()->for($borrower, 'borrower')->create();

        $this->actingAs($borrower)->get('/borrower')
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'conventional'));
    }

    public function test_an_administrator_stays_conventional_while_reviewing_a_shariah_campaign(): void
    {
        $campaign = Campaign::factory()->murabahah()->submitted()->create();

        $this->actingAs(User::factory()->admin()->create())->get("/admin/campaigns/{$campaign->id}")
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'conventional'));
    }
}
