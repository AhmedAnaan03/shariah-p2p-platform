<?php

namespace Tests\Feature\Investor;

use App\Models\Campaign;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The demo accounts can show PB-16, PB-17 and PB-31 in the browser: more than a page of open
 * campaigns, a Shariah-only investor, and a campaign with a repayment history.
 */
class MarketplaceDemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_data_fills_the_marketplace(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(12, Campaign::listed()->count());

        $this->actingAs(User::where('email', 'investor@example.com')->first())->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.total', 12)->where('campaigns.last_page', 2)->where('shariahOnly', false));

        $this->actingAs(User::where('email', 'shariah-investor@example.com')->first())->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.total', 7)->where('shariahOnly', true));

        $bakery = Campaign::listed()->where('title', 'Bakery deck oven and mixer')->firstOrFail();
        $this->actingAs(User::where('email', 'investor@example.com')->first())->get("/investor/campaigns/{$bakery->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('repaymentHistory.instalmentsDue', 8)
                ->where('repaymentHistory.paidOnTime', 7)
                ->where('repaymentHistory.paidLate', 1));
    }
}
