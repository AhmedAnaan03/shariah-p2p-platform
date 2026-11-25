<?php

namespace Tests\Feature;

use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Models\Campaign;
use App\Models\InvestorProfile;
use App\Models\User;
use App\Notifications\CampaignDecided;
use App\Services\CampaignReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CLAUDE.md and the design system: in Shariah mode the interface never says "interest" or
 * "guaranteed". This checks everything the server sends for a Shariah campaign: the borrower's
 * and the administrator's page props, the validation messages for the Shariah fields, and the
 * decision emails. (The form's own wording is checked by resources/js/lib/contracts.test.js.)
 */
class ShariahWordingTest extends TestCase
{
    use RefreshDatabase;

    private const FORBIDDEN = '/\b(interest|guaranteed)\b/i';

    /**
     * @return array<string, array{0: string}>
     */
    public static function shariahContracts(): array
    {
        return ['murabahah' => ['murabahah'], 'mudarabah' => ['mudarabah']];
    }

    #[DataProvider('shariahContracts')]
    public function test_the_borrower_and_admin_pages_for_a_shariah_campaign_never_say_interest_or_guaranteed(string $contract): void
    {
        $campaign = Campaign::factory()->{$contract}()->submitted()->create(['title' => 'Delivery van', 'description' => 'A van for deliveries to resorts and hotels.']);

        $borrowerPage = $this->actingAs($campaign->borrower)->get("/borrower/campaigns/{$campaign->id}")->assertOk();
        $adminPage = $this->actingAs(User::factory()->admin()->create())->get("/admin/campaigns/{$campaign->id}")->assertOk();

        foreach ([$borrowerPage, $adminPage] as $response) {
            $props = json_encode($response->viewData('page')['props']);
            $this->assertDoesNotMatchRegularExpression(self::FORBIDDEN, $props);
        }
    }

    #[DataProvider('shariahContracts')]
    public function test_the_shariah_terms_are_named_markup_or_profit_share(string $contract): void
    {
        $labels = array_column(Campaign::factory()->{$contract}()->make()->termsForDisplay(), 'label');

        $expected = $contract === 'murabahah'
            ? ['Asset being bought', 'Fixed markup', 'Total sale price']
            : ["Investors' profit share", "Business's profit share"];
        $this->assertSame($expected, $labels);
    }

    public function test_the_messages_for_shariah_fields_never_say_interest_or_guaranteed(): void
    {
        $messages = (new StoreCampaignRequest)->messages();
        $shariahFields = array_filter(
            $messages,
            fn (string $key) => ! str_starts_with($key, 'interest_rate_percent.') || $key === 'interest_rate_percent.prohibited_unless',
            ARRAY_FILTER_USE_KEY,
        );

        foreach ($shariahFields as $key => $message) {
            $this->assertDoesNotMatchRegularExpression(self::FORBIDDEN, $message, $key);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function decisions(): array
    {
        $cases = [];
        foreach (['murabahah', 'mudarabah'] as $contract) {
            foreach ([CampaignReviewService::APPROVE, CampaignReviewService::REJECT, CampaignReviewService::SUSPEND] as $decision) {
                $cases["{$contract} {$decision}"] = [$contract, $decision];
            }
        }

        return $cases;
    }

    #[DataProvider('decisions')]
    public function test_the_decision_emails_never_say_interest_or_guaranteed(string $contract, string $decision): void
    {
        $campaign = Campaign::factory()->{$contract}()->live()->create();
        $mail = (new CampaignDecided($campaign, $decision, 'A reason for the decision.'))->toMail($campaign->borrower);

        $text = implode(' ', [$mail->subject, ...$mail->introLines, ...$mail->outroLines, $mail->actionText]);
        $this->assertDoesNotMatchRegularExpression(self::FORBIDDEN, $text);
    }

    // ----- The marketplace and campaign page (PB-16, PB-17, PB-31) -----

    #[DataProvider('shariahContracts')]
    public function test_the_marketplace_and_campaign_page_for_a_shariah_campaign_never_say_interest_or_guaranteed(string $contract): void
    {
        $campaign = Campaign::factory()->{$contract}()->live()->create(['title' => 'Dive compressor', 'description' => 'A compressor for the dive shop.', 'business_background' => 'A dive shop since 2019.']);
        // A Shariah-only investor sees only Shariah campaigns, so the whole page is in Shariah mode.
        $investor = User::factory()->investor()->verified()->create();
        InvestorProfile::factory()->for($investor)->create(['shariah_only' => true, 'risk_acknowledged_at' => now()]);

        foreach (['/investor/campaigns', "/investor/campaigns/{$campaign->id}"] as $url) {
            $props = json_encode($this->actingAs($investor)->get($url)->assertOk()->viewData('page')['props']);
            $this->assertDoesNotMatchRegularExpression(self::FORBIDDEN, $props, $url);
        }
    }

    #[DataProvider('shariahContracts')]
    public function test_a_shariah_campaigns_return_is_never_shown_as_a_yearly_rate(string $contract): void
    {
        $campaign = Campaign::factory()->{$contract}()->make(['amount_requested' => 50_000, 'markup_amount' => $contract === 'murabahah' ? 4_000 : null, 'tenure_months' => 12]);
        $projected = $campaign->projectedReturn();

        foreach ([$campaign->rateForCard(), $projected['label'], $projected['value'], $projected['note']] as $text) {
            $this->assertDoesNotMatchRegularExpression(self::FORBIDDEN, $text);
            // "It is a total, not a yearly rate." is the one place the words may appear: to say it isn't one.
            $this->assertDoesNotMatchRegularExpression('/\b(a year|yearly|per annum|annual|p\.a\.)\b/i', str_replace('not a yearly rate', '', $text));
        }

        if ($contract === 'murabahah') {
            $this->assertSame('8.0% markup over 12 months', $projected['value']);
            $this->assertSame('8.0% in total', $campaign->rateForCard());
        }
    }
}
