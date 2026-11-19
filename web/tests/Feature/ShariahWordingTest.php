<?php

namespace Tests\Feature;

use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Models\Campaign;
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
}
