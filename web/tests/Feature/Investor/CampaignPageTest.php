<?php

namespace Tests\Feature\Investor;

use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Models\CampaignFinancials;
use App\Models\InvestorProfile;
use App\Models\RepaymentSchedule;
use App\Models\RiskScore;
use App\Models\User;
use App\Support\RepaymentHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-17: a campaign page that discloses the purpose, business background, amount, tenure,
 * projected return, the borrower's repayment history and the contract type, with the risk
 * score area reserved for PB-26.
 */
class CampaignPageTest extends TestCase
{
    use RefreshDatabase;

    private User $investor;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        $this->investor = User::factory()->investor()->verified()->create();
        InvestorProfile::factory()->for($this->investor)->create(['risk_acknowledged_at' => now()]);
    }

    private function sme(): User
    {
        return BorrowerProfile::factory()->create([
            'business_name' => 'Reef Dive Supplies', 'sector' => 'tourism', 'years_operating' => 6,
        ])->user;
    }

    /**
     * [contract, attributes, projected return label, projected return value]
     *
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string, 3: string}>
     */
    public static function contracts(): array
    {
        return [
            'conventional' => ['conventional', ['interest_rate_percent' => 9.25], 'Interest rate', '9.3% a year'],
            'murabahah' => ['murabahah', ['amount_requested' => 50_000, 'markup_amount' => 4_000], 'Expected markup', '8.0% markup over 12 months'],
            'mudarabah' => ['mudarabah', ['investor_profit_share_percent' => 60], 'Expected profit share', "60.0% of the business's actual profit"],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('contracts')]
    public function test_the_page_shows_every_disclosure(string $contract, array $attributes, string $returnLabel, string $returnValue): void
    {
        $campaign = Campaign::factory()->{$contract}()->live(now()->addDays(10))->for($this->sme(), 'borrower')->create([
            'title' => 'Dive compressor and tanks',
            'purpose' => 'growth',
            'description' => 'A compressor so we can fill tanks on the island.',
            'business_background' => 'Dive shop in Maafushi since 2019.',
            'tenure_months' => 12,
            ...$attributes,
        ]);

        $this->actingAs($this->investor)->get("/investor/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Investor/Campaigns/Show')
                ->where('campaign.title', 'Dive compressor and tanks')
                ->where('campaign.purpose_label', 'Growth')
                ->where('campaign.description', 'A compressor so we can fill tanks on the island.')
                ->where('campaign.business_background', 'Dive shop in Maafushi since 2019.')
                ->where('campaign.amount_requested', fn ($amount) => (float) $amount === (float) $campaign->amount_requested)
                ->where('campaign.tenure_months', 12)
                ->where('campaign.contract_type', $contract)
                ->has('campaign.terms')
                ->where('campaign.projected_return.label', $returnLabel)
                ->where('campaign.projected_return.value', $returnValue)
                ->has('campaign.projected_return.note')
                ->where('campaign.borrower.business_name', 'Reef Dive Supplies')
                ->where('campaign.borrower.sector_label', 'Tourism and hospitality')
                ->where('campaign.borrower.years_operating', 6)
                ->has('creditHistory', 3)
                ->where('repaymentHistory.fundedCampaigns', 0)
                ->where('repaymentHistory.summary', fn (string $text) => str_starts_with($text, 'No repayment history'))
                // Funding: 0% until pledging exists (PB-19).
                ->where('campaign.raised', 0)
                ->where('campaign.investors', 0)
                // The risk grade area is reserved (PB-26 fills it).
                ->where('risk', null)
                ->where('pledge.allowed', true)
                ->where('back.url', '/investor/campaigns')
                // Never the borrower's own details.
                ->missing('campaign.borrower.name')
                ->missing('campaign.borrower.email')
                ->missing('campaign.borrower.phone')
                ->missing('campaign.interest_rate_percent')
            );
    }

    public function test_a_murabahah_markup_is_a_total_over_the_tenure_never_a_yearly_rate(): void
    {
        $campaign = Campaign::factory()->murabahah()->make(['amount_requested' => 120_000, 'markup_amount' => 9_000, 'tenure_months' => 36]);

        $this->assertSame(7.5, $campaign->markupPercent());
        $this->assertSame('7.5% markup over 36 months', $campaign->projectedReturn()['value']);
        $this->assertSame('7.5% in total', $campaign->rateForCard());
        foreach ([$campaign->projectedReturn()['value'], $campaign->projectedReturn()['note'], $campaign->rateForCard()] as $text) {
            $this->assertDoesNotMatchRegularExpression('/\b(a year|yearly|per annum|annual|p\.a\.)\b/i', str_replace('not a yearly rate', '', $text));
        }
    }

    public function test_an_individual_borrowers_name_is_never_on_the_page(): void
    {
        $profile = BorrowerProfile::factory()->individual()->create();
        $profile->user->update(['name' => 'Fathimath Shaheema Private']);
        $campaign = Campaign::factory()->live()->for($profile->user, 'borrower')->create();

        $this->actingAs($this->investor)->get("/investor/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertDontSee('Fathimath Shaheema Private')
            ->assertDontSee($profile->user->email)
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaign.business_name', null)
                ->where('campaign.sector_label', 'Individual borrower')
                ->where('campaign.borrower.borrowing_as', 'Individual')
                ->where('campaign.borrower.sector_label', null));
    }

    /**
     * Campaigns that aren't open in the marketplace answer 404, as if they didn't exist.
     *
     * @return array<string, array{0: callable(): Campaign}>
     */
    public static function closedCampaigns(): array
    {
        return [
            'draft' => [fn () => Campaign::factory()->create()],
            'waiting for review' => [fn () => Campaign::factory()->submitted()->create()],
            'rejected' => [fn () => tap(Campaign::factory()->submitted()->create(), fn (Campaign $c) => $c->forceFill(['status' => 'rejected'])->save())],
            'suspended' => [fn () => tap(Campaign::factory()->live()->create(), fn (Campaign $c) => $c->forceFill(['status' => 'suspended'])->save())],
            'deadline passed' => [fn () => Campaign::factory()->live(now()->subMinute())->create()],
        ];
    }

    #[DataProvider('closedCampaigns')]
    public function test_a_campaign_that_is_not_open_is_not_found(callable $make): void
    {
        $campaign = $make();

        $this->actingAs($this->investor)->get("/investor/campaigns/{$campaign->id}")->assertNotFound();
    }

    public function test_an_unknown_or_non_numeric_id_is_not_found(): void
    {
        $this->actingAs($this->investor)->get('/investor/campaigns/999999')->assertNotFound();
        $this->actingAs($this->investor)->get('/investor/campaigns/abc')->assertNotFound();
    }

    public function test_the_campaign_page_is_for_investors_only(): void
    {
        $campaign = Campaign::factory()->live()->create();

        $this->get("/investor/campaigns/{$campaign->id}")->assertRedirect('/login');
        $this->actingAs($campaign->borrower)->get("/investor/campaigns/{$campaign->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get("/investor/campaigns/{$campaign->id}")->assertForbidden();
    }

    public function test_the_back_link_returns_to_the_filtered_marketplace(): void
    {
        $campaign = Campaign::factory()->live()->create();
        $from = '/investor/campaigns?sector=fisheries&page=2';

        $this->actingAs($this->investor)->get("/investor/campaigns/{$campaign->id}?return_to=".urlencode($from))
            ->assertInertia(fn (Assert $page) => $page
                ->where('back.url', $from)
                ->where('back.label', 'Back to the marketplace')
                ->where('back.crumb', 'Marketplace'));

        // Anything but one of the app's own pages falls back to the marketplace.
        $this->actingAs($this->investor)->get("/investor/campaigns/{$campaign->id}?return_to=".urlencode('https://evil.example'))
            ->assertInertia(fn (Assert $page) => $page->where('back.url', '/investor/campaigns'));
    }

    public function test_a_scored_campaign_shows_its_grade_in_the_reserved_area(): void
    {
        $campaign = Campaign::factory()->live()->create();
        $score = new RiskScore(['input_features' => [], 'grade' => 'B', 'probability_of_default' => 0.0825, 'model_version' => 'xgb-2026-12']);
        $score->campaign()->associate($campaign);
        $score->forceFill(['status' => RiskScore::STATUS_SCORED])->save();

        $this->actingAs($this->investor)->get("/investor/campaigns/{$campaign->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('risk.grade', 'B')
                ->where('risk.probability', 8.3)
                ->where('risk.model', 'xgb-2026-12'));
    }

    // ----- Repayment history -----

    private function instalment(Campaign $campaign, int $no, string $due, string $status, ?string $paidAt = null): void
    {
        $schedule = new RepaymentSchedule(['instalment_no' => $no, 'due_date' => $due, 'capital_due' => 1_000, 'return_due' => 50]);
        $schedule->campaign()->associate($campaign);
        $schedule->forceFill(['status' => $status, 'paid_at' => $paidAt])->save();
    }

    public function test_the_repayment_history_counts_earlier_instalments_already_due(): void
    {
        $borrower = $this->sme();
        $earlier = Campaign::factory()->submitted()->for($borrower, 'borrower')->create();
        $earlier->forceFill(['status' => 'repaying', 'funded_at' => now()->subMonths(5)])->save();
        $this->instalment($earlier, 1, '2026-07-18', 'paid', '2026-07-17 09:00:00');  // on time
        $this->instalment($earlier, 2, '2026-08-18', 'paid', '2026-08-18 18:00:00');  // on time: 23:00 in Malé, still the 18th
        $this->instalment($earlier, 3, '2026-09-18', 'paid', '2026-09-25 09:00:00');  // a week late
        $this->instalment($earlier, 4, '2026-10-18', 'late');                         // overdue
        $this->instalment($earlier, 5, '2026-11-18', 'due');                          // due today: not late yet
        $this->instalment($earlier, 6, '2026-12-18', 'due');                          // future: not counted

        $completed = Campaign::factory()->submitted()->for($borrower, 'borrower')->create();
        $completed->forceFill(['status' => 'completed'])->save();
        $this->instalment($completed, 1, '2026-01-10', 'paid', '2026-01-09 09:00:00');

        Campaign::factory()->submitted()->for($borrower, 'borrower')->create(); // never funded: not counted
        $campaign = Campaign::factory()->live()->for($borrower, 'borrower')->create();

        $this->actingAs($this->investor)->get("/investor/campaigns/{$campaign->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('repaymentHistory.fundedCampaigns', 2)
                ->where('repaymentHistory.completedCampaigns', 1)
                ->where('repaymentHistory.instalmentsDue', 5)
                ->where('repaymentHistory.paidOnTime', 3)
                ->where('repaymentHistory.paidLate', 1)
                ->where('repaymentHistory.overdue', 1)
                ->where('repaymentHistory.summary', '3 of 5 instalments paid on time, 1 paid late, 1 overdue now.'));
    }

    /**
     * [the record, the sentence]
     *
     * @return array<string, array{0: array<string, int>, 1: string}>
     */
    public static function summaries(): array
    {
        $none = ['fundedCampaigns' => 0, 'completedCampaigns' => 0, 'instalmentsDue' => 0, 'paidOnTime' => 0, 'paidLate' => 0, 'overdue' => 0];

        return [
            'no funded campaigns' => [$none, 'No repayment history on Amanah yet: this borrower has not had a campaign funded here before.'],
            'funded, nothing due yet' => [[...$none, 'fundedCampaigns' => 1], 'This borrower has had a campaign funded here, but no instalments have been due yet.'],
            'one instalment, on time' => [[...$none, 'fundedCampaigns' => 1, 'instalmentsDue' => 1, 'paidOnTime' => 1], '1 of 1 instalment paid on time.'],
            'all on time' => [[...$none, 'fundedCampaigns' => 1, 'instalmentsDue' => 12, 'paidOnTime' => 12], '12 of 12 instalments paid on time.'],
            'late and overdue' => [[...$none, 'fundedCampaigns' => 1, 'instalmentsDue' => 4, 'paidOnTime' => 1, 'paidLate' => 2, 'overdue' => 1], '1 of 4 instalments paid on time, 2 paid late, 1 overdue now.'],
        ];
    }

    /**
     * @param  array<string, int>  $record
     */
    #[DataProvider('summaries')]
    public function test_the_repayment_summary_sentence(array $record, string $expected): void
    {
        $this->assertSame($expected, RepaymentHistory::summary($record));
    }

    public function test_the_snapshot_and_not_the_current_profile_is_shown(): void
    {
        $campaign = Campaign::factory()->live()->create();
        $this->assertInstanceOf(CampaignFinancials::class, $campaign->financials);
        BorrowerProfile::factory()->for($campaign->borrower)->create(['late_payments_2y' => 7]);
        $campaign->financials->update(['late_payments_2y' => 2]);

        $this->actingAs($this->investor)->get("/investor/campaigns/{$campaign->id}")
            ->assertInertia(fn (Assert $page) => $page->where('creditHistory.1.value', '2'));
    }
}
