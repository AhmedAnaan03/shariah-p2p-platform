<?php

namespace Tests\Feature\Investor;

use App\Models\Campaign;
use App\Models\InvestorProfile;
use App\Models\User;
use App\Policies\CampaignPolicy;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PB-31: Shariah-only marketplace mode. The toggle persists per investor; while it is on,
 * non-compliant (conventional) campaigns are hidden from the marketplace, can't be opened by
 * their address and can't be pledged to. All of it is enforced on the server.
 */
class ShariahOnlyMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $conventional;

    private Campaign $murabahah;

    private Campaign $mudarabah;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        $this->conventional = Campaign::factory()->conventional()->live(now()->addDays(1))->create(['title' => 'Conventional']);
        $this->murabahah = Campaign::factory()->murabahah()->live(now()->addDays(2))->create(['title' => 'Murabahah']);
        $this->mudarabah = Campaign::factory()->mudarabah()->live(now()->addDays(3))->create(['title' => 'Mudarabah']);
    }

    private static function investor(bool $shariahOnly): User
    {
        $user = User::factory()->investor()->verified()->create();
        InvestorProfile::factory()->for($user)->create(['shariah_only' => $shariahOnly, 'risk_acknowledged_at' => now()]);

        return $user;
    }

    /**
     * @return list<string>
     */
    private function titles(User $investor, string $query = ''): array
    {
        return collect($this->actingAs($investor)->get("/investor/campaigns{$query}")
            ->assertOk()->viewData('page')['props']['campaigns']['data'])->pluck('title')->all();
    }

    // ----- Marketplace -----

    public function test_with_shariah_only_off_every_open_campaign_is_listed(): void
    {
        $this->assertSame(['Conventional', 'Murabahah', 'Mudarabah'], $this->titles(self::investor(false)));
    }

    public function test_with_shariah_only_on_only_murabahah_and_mudarabah_are_listed(): void
    {
        $investor = self::investor(true);

        $this->assertSame(['Murabahah', 'Mudarabah'], $this->titles($investor));
        $this->assertSame(['Murabahah', 'Mudarabah'], $this->titles($investor, '?contract=conventional'), 'Asking for conventional in the URL is ignored.');
        $this->assertSame(['Mudarabah'], $this->titles($investor, '?contract=mudarabah'));
        $this->assertSame([], $this->titles($investor, '?search=conventional'), 'A search can\'t reach a hidden campaign either.');

        $this->actingAs($investor)->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page
                ->where('shariahOnly', true)
                ->where('filterOptions.contract', [
                    ['value' => 'murabahah', 'label' => 'Murabahah'],
                    ['value' => 'mudarabah', 'label' => 'Mudarabah'],
                ]));
    }

    public function test_has_any_counts_only_what_a_shariah_only_investor_may_see(): void
    {
        $this->murabahah->forceFill(['status' => 'suspended'])->save();
        $this->mudarabah->forceFill(['status' => 'suspended'])->save();

        $this->actingAs(self::investor(true))->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page->where('hasAny', false)->has('campaigns.data', 0));
        $this->actingAs(self::investor(false))->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page->where('hasAny', true));
    }

    public function test_the_toggle_persists_and_changes_the_marketplace(): void
    {
        $investor = self::investor(false);

        $this->actingAs($investor)->patch('/investor/preferences/shariah-only', ['shariah_only' => true])->assertRedirect();
        $this->assertTrue($investor->investorProfile->fresh()->shariah_only);
        $this->assertSame(['Murabahah', 'Mudarabah'], $this->titles($investor->fresh()));

        $this->actingAs($investor)->patch('/investor/preferences/shariah-only', ['shariah_only' => false])->assertRedirect();
        $this->assertSame(['Conventional', 'Murabahah', 'Mudarabah'], $this->titles($investor->fresh()));
    }

    // ----- Campaign page -----

    public function test_a_shariah_only_investor_opening_a_conventional_campaign_is_sent_back_with_the_reason(): void
    {
        $this->actingAs(self::investor(true))->get("/investor/campaigns/{$this->conventional->id}")
            ->assertRedirect('/investor/campaigns')
            ->assertSessionHas('notice', CampaignPolicy::SHARIAH_ONLY_HIDDEN);
    }

    public function test_a_shariah_only_investor_can_open_shariah_campaigns(): void
    {
        $investor = self::investor(true);

        $this->actingAs($investor)->get("/investor/campaigns/{$this->murabahah->id}")->assertOk();
        $this->actingAs($investor)->get("/investor/campaigns/{$this->mudarabah->id}")->assertOk();
    }

    public function test_with_shariah_only_off_a_conventional_campaign_opens(): void
    {
        $this->actingAs(self::investor(false))->get("/investor/campaigns/{$this->conventional->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('campaign.contract_type', 'conventional'));
    }

    public function test_turning_shariah_only_on_while_on_a_conventional_campaign_lands_on_the_marketplace(): void
    {
        $investor = self::investor(false);
        $page = "/investor/campaigns/{$this->conventional->id}";

        $this->actingAs($investor)->from($page)->followingRedirects()
            ->patch('/investor/preferences/shariah-only', ['shariah_only' => true])
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Investor/Campaigns/Index')
                ->where('flash.notice', CampaignPolicy::SHARIAH_ONLY_HIDDEN)
                ->where('flash.status', 'Shariah-only mode is on.'));
    }

    // ----- Pledging -----

    public function test_the_pledge_gate_refuses_a_conventional_campaign_while_shariah_only_is_on(): void
    {
        $shariahOnly = self::investor(true);
        $refusal = Gate::forUser($shariahOnly)->inspect('pledge', $this->conventional);

        $this->assertFalse($refusal->allowed());
        $this->assertSame(AppServiceProvider::PLEDGE_SHARIAH_ONLY, $refusal->message());
        $this->assertTrue(Gate::forUser($shariahOnly)->allows('pledge', $this->murabahah));
        $this->assertTrue(Gate::forUser($shariahOnly)->allows('pledge', $this->mudarabah));
        $this->assertTrue(Gate::forUser(self::investor(false))->allows('pledge', $this->conventional));
    }

    public function test_the_pledge_gate_refuses_a_campaign_that_is_not_open(): void
    {
        $this->murabahah->forceFill(['status' => 'suspended'])->save();

        $refusal = Gate::forUser(self::investor(true))->inspect('pledge', $this->murabahah);

        $this->assertFalse($refusal->allowed());
        $this->assertSame(AppServiceProvider::PLEDGE_CLOSED, $refusal->message());
    }

    public function test_the_campaign_page_shows_the_pledge_gate_for_that_campaign(): void
    {
        $this->actingAs(self::investor(true))->get("/investor/campaigns/{$this->murabahah->id}")
            ->assertInertia(fn (Assert $page) => $page->where('pledge.allowed', true)->where('pledge.reason', null));
    }
}
