<?php

namespace Tests\Feature;

use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Models\Campaign;
use App\Models\InvestorProfile;
use App\Models\User;
use App\Notifications\CampaignDecided;
use App\Notifications\CampaignDisbursed;
use App\Services\CampaignReviewService;
use App\Support\CampaignPresenter;
use App\Support\SchedulePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesWallets;
use Tests\Concerns\ShariahWording;
use Tests\TestCase;

/**
 * CLAUDE.md and the design system: in Shariah mode the interface never says "interest" or
 * "guaranteed". This checks everything the server sends for a Shariah campaign: the borrower's
 * and the administrator's page props, the validation messages for the Shariah fields, and the
 * decision emails. (The form's own wording is checked by resources/js/lib/contracts.test.js.)
 *
 * PB-34: also the schedule pages and payout emails of a paid-out Shariah campaign, and a
 * Mudarabah's planned capital return schedule, which must never read as a debt.
 */
class ShariahWordingTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    private const FORBIDDEN = ShariahWording::FORBIDDEN;

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

    // ----- The schedule and payout (PB-34, PB-28) -----

    #[DataProvider('shariahContracts')]
    public function test_the_schedule_pages_of_a_paid_out_shariah_campaign_never_say_interest_or_guaranteed(string $contract): void
    {
        Notification::fake();
        [$campaign, [$investor]] = $this->paidOutCampaign($contract, 10_000, 3, [10_000]);
        $investor->investorProfile->forceFill(['shariah_only' => true])->save();
        Carbon::setTestNow(now()->addMonths(2)); // some rows past their date

        foreach ([[$campaign->borrower, "/borrower/campaigns/{$campaign->id}"], [$investor, "/investor/campaigns/{$campaign->id}"]] as [$user, $url]) {
            $props = $this->actingAs($user)->get($url)->assertOk()->viewData('page')['props'];
            $this->assertNotNull($props['schedule'], $url);
            $this->assertDoesNotMatchRegularExpression(self::FORBIDDEN, json_encode($props), $url);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function payoutEmails(): array
    {
        $cases = [];
        foreach (['murabahah', 'mudarabah'] as $contract) {
            foreach ([CampaignDisbursed::BORROWER, CampaignDisbursed::INVESTOR] as $audience) {
                $cases["{$contract} {$audience}"] = [$contract, $audience];
            }
        }

        return $cases;
    }

    #[DataProvider('payoutEmails')]
    public function test_the_payout_emails_never_say_interest_or_guaranteed(string $contract, string $audience): void
    {
        $campaign = Campaign::factory()->{$contract}()->live()->create();
        $mail = (new CampaignDisbursed($campaign, $audience, '26 Dec 2026', 1_000_00, 5_000_00))->toMail($campaign->borrower);

        $text = implode(' ', [$mail->subject, ...$mail->introLines, ...$mail->outroLines, $mail->actionText]);
        $this->assertDoesNotMatchRegularExpression(self::FORBIDDEN, $text);

        if ($contract === 'mudarabah') {
            $this->assertDoesNotMatchRegularExpression(ShariahWording::DEBT, $text, 'A Mudarabah payout email never reads as a debt.');
            $this->assertStringContainsString('planned capital return', $text);
            $this->assertStringContainsString(SchedulePresenter::MUDARABAH_CAPITAL_NOTE, $text);
        }
    }

    public function test_a_mudarabah_schedule_is_labelled_planned_capital_return_everywhere(): void
    {
        $labels = SchedulePresenter::labels(Campaign::factory()->mudarabah()->make());

        $this->assertSame('Planned capital return schedule', $labels['title']);
        $this->assertSame('Planned capital return', $labels['row']);
        $this->assertSame('Planned capital returns', $labels['rows']);
        $this->assertSame('Planned capital return', $labels['capital']);
        $this->assertSame('Planned capital return', $labels['amount']);
        $this->assertSame('Planned date', $labels['date']);
        $this->assertNull($labels['return'], 'No return column: profit is reported later (PB-29).');
        foreach (array_filter($labels) as $label) {
            $this->assertDoesNotMatchRegularExpression(ShariahWording::DEBT, $label);
        }
    }

    public function test_the_mudarabah_notes_status_and_capital_wording_never_read_as_a_debt(): void
    {
        $campaign = Campaign::factory()->mudarabah()->create(['status' => Campaign::STATUS_REPAYING]);
        $standing = CampaignPresenter::standing($campaign);

        foreach ([SchedulePresenter::borrowerNote($campaign), SchedulePresenter::investorNote($campaign), $standing['title'], $standing['body'], SchedulePresenter::AWAITING_LABEL] as $text) {
            $this->assertDoesNotMatchRegularExpression(ShariahWording::DEBT, $text);
            $this->assertDoesNotMatchRegularExpression(self::FORBIDDEN, $text);
        }
        $this->assertSame('returning', $standing['status'], 'Its badge says "Returning capital", not "Repaying".');
        // The review's wording: results, proportional loss, and the negligence or misconduct exception.
        $this->assertStringContainsString('Capital is returned according to the business’s results.', SchedulePresenter::MUDARABAH_CAPITAL_NOTE);
        $this->assertStringContainsString('investors bear the loss of capital in proportion to their share, except in cases of negligence or misconduct', SchedulePresenter::MUDARABAH_CAPITAL_NOTE);
    }

    public function test_the_database_comments_describe_a_mudarabah_row_as_a_planned_capital_return(): void
    {
        $comments = DB::table('information_schema.COLUMNS')->whereRaw('TABLE_SCHEMA = database()')
            ->where('TABLE_NAME', 'repayment_schedules')->pluck('COLUMN_COMMENT', 'COLUMN_NAME');
        $table = DB::table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = database()')->where('TABLE_NAME', 'repayment_schedules')->value('TABLE_COMMENT');

        $this->assertStringContainsString('planned capital returns (Mudarabah, not a debt)', $table);
        $this->assertStringContainsString('planned capital return', $comments['capital_due']);
        $this->assertStringContainsString('not a debt due date', $comments['due_date']);
        $this->assertStringContainsString('always NULL for Mudarabah', $comments['return_due']);
    }
}
