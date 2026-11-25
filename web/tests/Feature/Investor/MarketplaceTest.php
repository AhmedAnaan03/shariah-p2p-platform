<?php

namespace Tests\Feature\Investor;

use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PB-15: approved campaigns appear in the marketplace with a funding deadline. PB-16's filters
 * and pages are in MarketplaceFiltersTest, PB-17's campaign page in CampaignPageTest and PB-31's
 * Shariah-only mode in ShariahOnlyMarketplaceTest.
 */
class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
    }

    public function test_only_live_campaigns_before_their_deadline_are_listed_closest_deadline_first(): void
    {
        $later = Campaign::factory()->live(now()->addDays(20))->create();
        $sooner = Campaign::factory()->live(now()->addDays(2))->create();
        Campaign::factory()->live(now()->subMinute())->create(); // deadline passed
        Campaign::factory()->submitted()->create();
        Campaign::factory()->live()->create()->forceFill(['status' => 'suspended'])->save();
        Campaign::factory()->submitted()->create()->forceFill(['status' => 'rejected'])->save();
        Campaign::factory()->create(); // draft

        $this->actingAs(User::factory()->investor()->create())->get('/investor/campaigns')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Investor/Campaigns/Index')
                ->has('campaigns.data', 2)
                ->where('campaigns.data.0.id', $sooner->id)
                ->where('campaigns.data.1.id', $later->id)
                ->where('campaigns.data.0.funding_deadline', $sooner->funding_deadline->toIso8601String())
            );
    }

    public function test_the_listing_shows_an_smes_business_name_but_never_an_individuals_name(): void
    {
        $sme = BorrowerProfile::factory()->create(['business_name' => 'Hulhumalé Fresh Fish'])->user;
        $individual = BorrowerProfile::factory()->individual()->create()->user;
        Campaign::factory()->live(now()->addDays(1))->for($sme, 'borrower')->create();
        Campaign::factory()->live(now()->addDays(2))->for($individual, 'borrower')->create();

        $this->actingAs(User::factory()->investor()->create())->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaigns.data.0.business_name', 'Hulhumalé Fresh Fish')
                ->where('campaigns.data.1.business_name', null)
                ->missing('campaigns.data.0.borrower')
                ->missing('campaigns.data.0.interest_rate_percent')
            )
            ->assertDontSee($individual->name);
    }

    public function test_the_marketplace_is_for_investors_only(): void
    {
        $this->get('/investor/campaigns')->assertRedirect('/login');
        $this->actingAs(User::factory()->borrower()->verified()->create())->get('/investor/campaigns')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/investor/campaigns')->assertForbidden();
    }

    public function test_the_listed_scope_is_live_and_before_the_deadline(): void
    {
        $open = Campaign::factory()->live(now()->addSecond())->create();
        Campaign::factory()->live(now())->create(); // closes exactly now: no longer open

        $this->assertSame([$open->id], Campaign::listed()->pluck('id')->all());
    }
}
