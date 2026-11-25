<?php

namespace Tests\Feature\Investor;

use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\RiskScore;
use App\Models\User;
use App\Support\MarketplaceFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-16: browse, search and filter the marketplace. Filters for sector, purpose, amount range,
 * tenure, contract (Shariah mode) and risk grade; keyword search; paginated results.
 *
 * Every filter is tested with a blank value (no filter), an invalid value (ignored, so every
 * campaign is listed) and valid values, including the range boundaries.
 */
class MarketplaceFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $investor;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        $this->investor = User::factory()->investor()->verified()->create();
    }

    /**
     * The titles the marketplace lists for a query string, in order.
     *
     * @return list<string>
     */
    private function titles(string $query = ''): array
    {
        return collect($this->actingAs($this->investor)->get("/investor/campaigns{$query}")
            ->assertOk()->viewData('page')['props']['campaigns']['data'])->pluck('title')->all();
    }

    /**
     * A live campaign closing in $days days (so the order is known), for an SME in $sector or
     * an individual when $sector is null.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(string $title, int $days, array $attributes = [], ?string $sector = 'retail', string $contract = 'conventional'): Campaign
    {
        $profile = $sector === null
            ? BorrowerProfile::factory()->individual()->create()
            : BorrowerProfile::factory()->create(['sector' => $sector]);

        return Campaign::factory()->{$contract}()->live(now()->addDays($days))
            ->for($profile->user, 'borrower')->create(['title' => $title, ...$attributes]);
    }

    private function score(Campaign $campaign, ?string $grade, string $status = 'scored'): RiskScore
    {
        $score = new RiskScore(['input_features' => [], 'grade' => $grade, 'probability_of_default' => $grade ? 0.08 : null]);
        $score->campaign()->associate($campaign);
        $score->forceFill(['status' => $status])->save();

        return $score;
    }

    // ----- Sector -----

    public function test_the_sector_filter(): void
    {
        $this->campaign('Fish', 1, sector: 'fisheries');
        $this->campaign('Shop', 2, sector: 'retail');
        $this->campaign('Tuition', 3, sector: null);

        $this->assertSame(['Fish', 'Shop', 'Tuition'], $this->titles('?sector='), 'Blank: every campaign.');
        $this->assertSame(['Fish', 'Shop', 'Tuition'], $this->titles('?sector=space_mining'), 'Invalid: ignored.');
        $this->assertSame(['Fish'], $this->titles('?sector=fisheries'));
        $this->assertSame(['Tuition'], $this->titles('?sector=individual'), 'Individual borrowers have no sector.');
        $this->assertSame([], $this->titles('?sector=tourism'));
    }

    // ----- Purpose -----

    public function test_the_purpose_filter(): void
    {
        $this->campaign('Start', 1, ['purpose' => 'start_up']);
        $this->campaign('Grow', 2, ['purpose' => 'growth']);

        $this->assertSame(['Start', 'Grow'], $this->titles('?purpose=holiday'), 'Invalid: ignored.');
        $this->assertSame(['Grow'], $this->titles('?purpose=growth'));
    }

    // ----- Amount (the two-handle slider) -----

    /**
     * [query, titles listed, filters echoed back]: both ends are inclusive; anything that isn't
     * a whole number is ignored; out-of-range values are clamped, and an end at its limit filters
     * nothing; a reversed pair is swapped.
     *
     * @return array<string, array{0: string, 1: list<string>, 2: array<string, int>}>
     */
    public static function amountRanges(): array
    {
        $all = ['10K', '50K', '50K+1c', '200K', '500K', '2M'];

        return [
            'blank' => ['?amount_min=&amount_max=', $all, []],
            'not numbers' => ['?amount_min=abc&amount_max=1e6', $all, []],
            'negative and decimal' => ['?amount_min=-5&amount_max=2.5', $all, []],
            'array' => ['?amount_min[]=50000', $all, []],
            'minimum only (inclusive)' => ['?amount_min=50000', ['50K', '50K+1c', '200K', '500K', '2M'], ['amount_min' => 50_000]],
            'maximum only (inclusive)' => ['?amount_max=50000', ['10K', '50K'], ['amount_max' => 50_000]],
            'both' => ['?amount_min=50001&amount_max=500000', ['200K', '500K'], ['amount_min' => 50_001, 'amount_max' => 500_000]],
            'reversed: swapped' => ['?amount_min=500000&amount_max=50001', ['200K', '500K'], ['amount_min' => 50_001, 'amount_max' => 500_000]],
            'below the lowest: clamped, filters nothing' => ['?amount_min=5', $all, []],
            'above the highest: clamped, filters nothing' => ['?amount_max=99999999', $all, []],
            'above the highest as a minimum: clamped to 2,000,000' => ['?amount_min=99999999', ['2M'], ['amount_min' => 2_000_000]],
            'exactly the limits: filters nothing' => ['?amount_min=10000&amount_max=2000000', $all, []],
        ];
    }

    /**
     * @param  list<string>  $expected
     * @param  array<string, int>  $echoed
     */
    #[DataProvider('amountRanges')]
    public function test_the_amount_range_slider_filter(string $query, array $expected, array $echoed): void
    {
        $amounts = ['10K' => 10_000, '50K' => 50_000, '50K+1c' => 50_000.01, '200K' => 200_000, '500K' => 500_000, '2M' => 2_000_000];
        $day = 0;
        foreach ($amounts as $title => $amount) {
            $this->campaign($title, ++$day, ['amount_requested' => $amount]);
        }

        $this->assertSame($expected, $this->titles($query));
        $this->actingAs($this->investor)->get("/investor/campaigns{$query}")
            ->assertInertia(fn (Assert $page) => $page->where('filters', $echoed));
    }

    public function test_the_page_gets_the_sliders_scale(): void
    {
        $this->actingAs($this->investor)->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page
                ->where('amountSlider.min', 10_000)
                ->where('amountSlider.max', 2_000_000)
                ->where('amountSlider.steps', MarketplaceFilters::AMOUNT_STEPS));

        $steps = MarketplaceFilters::AMOUNT_STEPS;
        $this->assertSame(Campaign::MIN_AMOUNT, $steps[0]);
        $this->assertSame(Campaign::MAX_AMOUNT, end($steps));
        $sorted = $steps;
        sort($sorted);
        $this->assertSame($sorted, $steps, 'The steps go up.');
        // Small amounts get their share of the track: at least a quarter of the steps are under MVR 50,000.
        $this->assertGreaterThanOrEqual(count($steps) / 4, count(array_filter($steps, fn (int $step) => $step < 50_000)));
    }

    // ----- Tenure -----

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function tenureRanges(): array
    {
        return [
            'blank' => ['', ['3', '12', '18', '36', '48', '60']],
            'invalid' => ['forever', ['3', '12', '18', '36', '48', '60']],
            'up to 12 months (inclusive)' => ['up_to_12', ['3', '12']],
            '13 to 36 months' => ['13_to_36', ['18', '36']],
            'over 36 months' => ['over_36', ['48', '60']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('tenureRanges')]
    public function test_the_tenure_filter(string $value, array $expected): void
    {
        foreach ([3, 12, 18, 36, 48, 60] as $index => $months) {
            $this->campaign((string) $months, $index + 1, ['tenure_months' => $months]);
        }

        $this->assertSame($expected, $this->titles("?tenure={$value}"));
    }

    // ----- Contract (Shariah mode) -----

    public function test_the_contract_filter_including_any_shariah_contract(): void
    {
        $this->campaign('Conventional', 1);
        $this->campaign('Murabahah', 2, contract: 'murabahah');
        $this->campaign('Mudarabah', 3, contract: 'mudarabah');

        $this->assertSame(['Conventional', 'Murabahah', 'Mudarabah'], $this->titles('?contract=ijarah'), 'Invalid: ignored.');
        $this->assertSame(['Conventional'], $this->titles('?contract=conventional'));
        $this->assertSame(['Mudarabah'], $this->titles('?contract=mudarabah'));
        $this->assertSame(['Murabahah', 'Mudarabah'], $this->titles('?contract=shariah'));
    }

    // ----- Risk grade -----

    public function test_the_risk_grade_filter_uses_the_latest_successful_score(): void
    {
        $this->score($a = $this->campaign('Graded A', 1), 'A');
        $this->score($this->campaign('Never scored', 2), null, 'pending');
        $this->campaign('No score at all', 3);
        $regraded = $this->campaign('Was A, now C', 4);
        $this->score($regraded, 'A');
        $this->score($regraded, 'C');
        $this->score($regraded, null, 'failed'); // a later failed attempt doesn't hide the C

        $this->assertSame(['Graded A', 'Never scored', 'No score at all', 'Was A, now C'], $this->titles('?grade=Z'), 'Invalid: ignored.');
        $this->assertSame(['Graded A'], $this->titles('?grade=A'));
        $this->assertSame(['Was A, now C'], $this->titles('?grade=C'));
        $this->assertSame(['Never scored', 'No score at all'], $this->titles('?grade=unscored'));
        $this->assertSame([], $this->titles('?grade=E'));

        $this->actingAs($this->investor)->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaigns.data.0.grade', 'A')
                ->where('campaigns.data.1.grade', null) // the card says "Not yet scored"
                ->where('campaigns.data.3.grade', 'C'));
    }

    // ----- Search and combinations -----

    public function test_filters_combine_with_the_search_and_are_echoed_back(): void
    {
        $this->campaign('Cold room', 1, ['purpose' => 'growth', 'tenure_months' => 24], 'fisheries');
        $this->campaign('Cold storage van', 2, ['purpose' => 'start_up', 'tenure_months' => 24], 'fisheries');
        $this->campaign('Cold drinks kiosk', 3, ['purpose' => 'growth', 'tenure_months' => 6], 'retail');

        $this->assertSame(['Cold room'], $this->titles('?search=cold&sector=fisheries&purpose=growth&tenure=13_to_36&amount_max=60000'));

        $this->actingAs($this->investor)->get('/investor/campaigns?search=cold&sector=fisheries&amount_min=bogus&tenure=forever')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters', ['search' => 'cold', 'sector' => 'fisheries']) // the invalid amount and tenure are dropped
                ->has('filterOptions.sector', count(BorrowerProfile::SECTORS) + 1)
                ->missing('filterOptions.amount') // a slider, not a dropdown
                ->has('filterOptions.tenure', 3)
                ->has('filterOptions.contract', 4)
                ->has('filterOptions.grade', 6)
                ->where('filterOptions.grade.5', ['value' => 'unscored', 'label' => 'Not yet scored']));
    }

    // ----- Pagination -----

    public function test_results_are_paginated_nine_to_a_page_and_pages_keep_the_filters(): void
    {
        foreach (range(1, 11) as $day) {
            $this->campaign(sprintf('Campaign %02d', $day), $day, ['purpose' => 'growth']);
        }
        $this->campaign('Other purpose', 12, ['purpose' => 'personal']);

        $this->actingAs($this->investor)->get('/investor/campaigns?purpose=growth')
            ->assertInertia(fn (Assert $page) => $page
                ->has('campaigns.data', MarketplaceFilters::PER_PAGE)
                ->where('campaigns.current_page', 1)
                ->where('campaigns.last_page', 2)
                ->where('campaigns.total', 11)
                ->where('campaigns.from', 1)
                ->where('campaigns.to', 9)
                ->where('campaigns.prev_url', null)
                ->where('campaigns.next_url', fn (string $url) => str_contains($url, 'purpose=growth') && str_contains($url, 'page=2'))
                ->has('campaigns.pages', 2)
                ->where('campaigns.pages.0', fn ($link) => $link['label'] === '1' && $link['active'] === true));

        $this->assertSame(['Campaign 10', 'Campaign 11'], $this->titles('?purpose=growth&page=2'));
        $this->assertSame('Campaign 01', $this->titles('?purpose=growth&page=abc')[0], 'A page that is not a number is page 1.');
    }

    public function test_a_page_past_the_end_goes_to_the_last_page(): void
    {
        foreach (range(1, 10) as $day) {
            $this->campaign("Campaign {$day}", $day);
        }

        $response = $this->actingAs($this->investor)->get('/investor/campaigns?page=7');

        $response->assertRedirect();
        $this->assertStringContainsString('page=2', $response->headers->get('Location'));
    }

    // ----- What a card gets -----

    public function test_each_card_has_the_return_figure_with_its_unit_and_its_funding(): void
    {
        $conventional = $this->campaign('Conventional', 1, ['interest_rate_percent' => 8.5]);
        $this->campaign('Murabahah', 2, ['amount_requested' => 50_000, 'markup_amount' => 4_000], contract: 'murabahah');
        $this->campaign('Mudarabah', 3, ['investor_profit_share_percent' => 60], contract: 'mudarabah');

        // Two investors pledge; a refunded pledge doesn't count.
        [$first, $second] = User::factory()->investor()->count(2)->create();
        foreach ([[$first, 1_000, 'held'], [$first, 500, 'settled'], [$second, 2_000, 'held'], [$second, 9_999, 'refunded']] as [$who, $amount, $status]) {
            $pledge = new Pledge(['amount' => $amount]);
            $pledge->forceFill(['campaign_id' => $conventional->id, 'investor_id' => $who->id, 'status' => $status])->save();
        }

        $this->actingAs($this->investor)->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaigns.data.0.rate', '8.5%')
                ->where('campaigns.data.0.rate_unit', 'a year')
                ->where('campaigns.data.0.raised', 3_500)
                ->where('campaigns.data.0.investors', 2)
                ->where('campaigns.data.1.rate', '8.0%')
                ->where('campaigns.data.1.rate_unit', 'in total') // a markup for the whole tenure, never a yearly rate
                ->where('campaigns.data.1.raised', 0)
                ->where('campaigns.data.1.investors', 0)
                ->where('campaigns.data.2.rate', '60 : 40')
                ->where('campaigns.data.2.rate_unit', 'investors : business')
                ->where('campaigns.data.0.sector_label', 'Retail and trade')
                ->where('campaigns.data.0.url', "/investor/campaigns/{$conventional->id}"));
    }
}
