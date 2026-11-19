<?php

namespace Tests\Feature\Borrower;

use App\Models\AuditLog;
use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Models\CampaignFinancials;
use App\Models\KycDocument;
use App\Models\User;
use App\Services\KycDocumentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\MakesKycFiles;
use Tests\TestCase;

/**
 * PB-13: a verified borrower creates a financing request with amount, purpose, tenure and
 * contract; it enters 'submitted'. PB-14: the scoring fields are captured as structured data,
 * missing ones block submission with clear messages, and an MMA credit report can be attached.
 */
class CampaignSubmissionTest extends TestCase
{
    use MakesKycFiles, RefreshDatabase;

    private User $borrower;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        Storage::fake(KycDocumentStore::DISK);

        $this->borrower = BorrowerProfile::factory()->create(['declared_annual_income' => 480_000])->user;
        $this->borrower->forceFill(['status' => 'verified'])->save();
    }

    /**
     * A complete, valid conventional request: the first applicant in the shared feature
     * fixture, plus the request details.
     *
     * @return array<string, mixed>
     */
    public static function validInput(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Second delivery van for Malé routes',
            'purpose' => 'growth',
            'description' => 'A second refrigerated van so we can deliver fish to hotels twice a day.',
            'business_background' => 'Family fish wholesaler, trading since 2015.',
            'amount_requested' => '120000',
            'tenure_months' => '36',
            'contract_type' => 'conventional',
            'interest_rate_percent' => '8.5',
            'annual_income' => '480000',
            'monthly_debt_repayments' => '6000',
            'employment_years' => '7',
            'housing' => 'own_with_home_loan',
            'open_credit_lines' => '3',
            'late_payments_2y' => '1',
            'public_records' => '0',
        ], $overrides);
    }

    private function submit(array $input, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->borrower)
            ->from('/borrower/campaigns/create')
            ->post('/borrower/campaigns', $input);
    }

    // ---- Who may submit (PB-13: only verified borrowers) ---------------------------------

    public function test_a_verified_borrower_submits_a_request_that_enters_the_submitted_state(): void
    {
        $response = $this->submit(self::validInput());

        $campaign = Campaign::sole();
        $response->assertRedirect("/borrower/campaigns/{$campaign->id}")
            ->assertSessionHas('status', 'Your request has been submitted. An administrator will review it before it is shown to investors.');

        $this->assertSame('submitted', $campaign->status);
        $this->assertTrue($campaign->submitted_at->equalTo(now()));
        $this->assertSame($this->borrower->id, $campaign->borrower_id);
        $this->assertSame('120000.00', $campaign->amount_requested);
        $this->assertSame(36, $campaign->tenure_months);
        $this->assertSame('growth', $campaign->purpose);
        $this->assertSame('8.50', $campaign->interest_rate_percent);
        $this->assertNull($campaign->funding_deadline);
        $this->assertNull($campaign->approved_at);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedStatuses(): array
    {
        return [
            'pending verification' => ['pending_verification'],
            'rejected' => ['rejected'],
            'suspended' => ['suspended'],
        ];
    }

    #[DataProvider('blockedStatuses')]
    public function test_a_borrower_who_is_not_verified_cannot_submit(string $status): void
    {
        $this->borrower->forceFill(['status' => $status])->save();

        $this->submit(self::validInput())->assertForbidden();

        $this->assertSame(0, Campaign::count());
    }

    #[DataProvider('blockedStatuses')]
    public function test_a_borrower_who_is_not_verified_sees_why_instead_of_the_form(string $status): void
    {
        $this->borrower->forceFill(['status' => $status])->save();

        $this->actingAs($this->borrower)->get('/borrower/campaigns/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Borrower/Campaigns/Create')
                ->where('canSubmit', false)
                ->where('blockedReason', 'Your account must be verified before you can submit a financing request.')
            );
    }

    public function test_investors_and_administrators_cannot_open_or_post_to_the_borrower_routes(): void
    {
        foreach ([User::factory()->investor()->verified()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get('/borrower/campaigns')->assertForbidden();
            $this->actingAs($user)->get('/borrower/campaigns/create')->assertForbidden();
            $this->actingAs($user)->post('/borrower/campaigns', self::validInput())->assertForbidden();
        }

        $this->assertSame(0, Campaign::count());
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/borrower/campaigns/create')->assertRedirect('/login');
        $this->post('/borrower/campaigns', self::validInput())->assertRedirect('/login');
    }

    // ---- The form (PB-13) ------------------------------------------------------------------

    public function test_the_form_offers_the_four_purposes_and_prefills_the_income_from_the_profile(): void
    {
        $this->actingAs($this->borrower)->get('/borrower/campaigns/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Borrower/Campaigns/Create')
                ->where('canSubmit', true)
                ->where('purposes', [
                    ['value' => 'start_up', 'label' => 'Start-up'],
                    ['value' => 'growth', 'label' => 'Growth'],
                    ['value' => 'education', 'label' => 'Education'],
                    ['value' => 'personal', 'label' => 'Personal'],
                ])
                ->where('declaredAnnualIncome', '480000.00')
                ->has('tenures', count(Campaign::TENURES))
                ->has('housing', 4)
                ->has('employmentYears', 11)
                ->where('limits.minAmount', Campaign::MIN_AMOUNT)
                ->where('limits.maxAmount', Campaign::MAX_AMOUNT)
            );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function purposes(): array
    {
        return ['start-up' => ['start_up'], 'growth' => ['growth'], 'education' => ['education'], 'personal' => ['personal']];
    }

    #[DataProvider('purposes')]
    public function test_each_purpose_can_be_chosen(string $purpose): void
    {
        $this->submit(self::validInput(['purpose' => $purpose]))->assertSessionHasNoErrors();

        $this->assertSame($purpose, Campaign::sole()->purpose);
    }

    // ---- Conventional or Shariah-compliant (PB-13) ----------------------------------------

    public function test_a_murabahah_request_stores_markup_and_asset_and_no_rate(): void
    {
        $this->submit(self::validInput([
            'contract_type' => 'murabahah',
            'interest_rate_percent' => null,
            'markup_amount' => '9000',
            'asset_description' => 'Refrigerated delivery van',
        ]))->assertSessionHasNoErrors();

        $campaign = Campaign::sole();
        $this->assertTrue($campaign->isShariah());
        $this->assertSame('9000.00', $campaign->markup_amount);
        $this->assertSame('Refrigerated delivery van', $campaign->asset_description);
        $this->assertNull($campaign->interest_rate_percent);
        $this->assertNull($campaign->investor_profit_share_percent);
    }

    public function test_a_mudarabah_request_stores_the_profit_share_and_no_rate(): void
    {
        $this->submit(self::validInput([
            'contract_type' => 'mudarabah',
            'interest_rate_percent' => null,
            'investor_profit_share_percent' => '60',
        ]))->assertSessionHasNoErrors();

        $campaign = Campaign::sole();
        $this->assertTrue($campaign->isShariah());
        $this->assertSame('60.00', $campaign->investor_profit_share_percent);
        $this->assertNull($campaign->interest_rate_percent);
        $this->assertNull($campaign->markup_amount);
    }

    /**
     * A Shariah request that also carries an interest rate is refused by validation, before
     * the database CHECK would refuse it.
     *
     * @return array<string, array{0: array<string, string>}>
     */
    public static function shariahWithARate(): array
    {
        return [
            'murabahah with a rate' => [['contract_type' => 'murabahah', 'markup_amount' => '9000', 'asset_description' => 'Delivery van', 'interest_rate_percent' => '5']],
            'mudarabah with a rate' => [['contract_type' => 'mudarabah', 'investor_profit_share_percent' => '60', 'interest_rate_percent' => '5']],
            'mudarabah with a 0% rate' => [['contract_type' => 'mudarabah', 'investor_profit_share_percent' => '60', 'interest_rate_percent' => '0']],
        ];
    }

    #[DataProvider('shariahWithARate')]
    public function test_a_shariah_request_with_any_rate_is_refused(array $overrides): void
    {
        $this->submit(self::validInput($overrides))
            ->assertSessionHasErrors(['interest_rate_percent' => 'Only a conventional request can carry a rate. Remove it for a Murabahah or Mudarabah contract.']);

        $this->assertSame(0, Campaign::count());
    }

    public function test_other_contracts_terms_are_refused_on_a_conventional_request(): void
    {
        $this->submit(self::validInput(['markup_amount' => '9000', 'asset_description' => 'Van', 'investor_profit_share_percent' => '60']))
            ->assertSessionHasErrors([
                'markup_amount' => 'A markup only applies to a Murabahah contract.',
                'asset_description' => 'An asset description only applies to a Murabahah contract.',
                'investor_profit_share_percent' => 'A profit share only applies to a Mudarabah contract.',
            ]);
    }

    // ---- Validation of every input (blank, invalid, valid) ----------------------------------

    /**
     * [field overrides, field, expected message]. Blank and invalid cases for each input.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidInputs(): array
    {
        $count = 'Enter a whole number from 0 to 99. Enter 0 if none.';

        return [
            // PB-13: the request
            'title blank' => [['title' => ''], 'title', 'Give your request a title.'],
            'title too short' => [['title' => 'Van'], 'title', 'Make the title at least 5 characters.'],
            'title too long' => [['title' => str_repeat('a', 151)], 'title', 'Keep the title to 150 characters or fewer.'],
            'purpose blank' => [['purpose' => ''], 'purpose', 'Choose what the financing is for.'],
            'purpose not offered' => [['purpose' => 'holiday'], 'purpose', 'Choose what the financing is for from the list.'],
            'description blank' => [['description' => ''], 'description', 'Describe what the money will be used for.'],
            'description too short' => [['description' => 'A van.'], 'description', 'Write at least 30 characters so investors understand the request.'],
            'background too long' => [['business_background' => str_repeat('a', 5001)], 'business_background', 'Keep the background to 5,000 characters or fewer.'],
            'amount blank' => [['amount_requested' => ''], 'amount_requested', 'Enter the amount you need.'],
            'amount not a number' => [['amount_requested' => 'lots'], 'amount_requested', 'Enter the amount as a number, like 120000.'],
            'amount with 3 decimals' => [['amount_requested' => '120000.505'], 'amount_requested', 'Enter the amount with at most 2 decimal places.'],
            'amount below the minimum' => [['amount_requested' => '9999.99'], 'amount_requested', 'The amount must be at least MVR 10,000.'],
            'amount above the maximum' => [['amount_requested' => '2000000.01'], 'amount_requested', 'The amount must be MVR 2,000,000 or less.'],
            'tenure blank' => [['tenure_months' => ''], 'tenure_months', 'Choose how many months you need to repay.'],
            'tenure not offered' => [['tenure_months' => '7'], 'tenure_months', 'Choose a tenure from the list.'],
            'tenure not a number' => [['tenure_months' => 'year'], 'tenure_months', 'Choose a tenure from the list.'],
            'contract blank' => [['contract_type' => ''], 'contract_type', 'Choose a contract type.'],
            'contract not offered' => [['contract_type' => 'ijarah'], 'contract_type', 'Choose a contract type from the list.'],

            // PB-13: contract terms
            'rate blank on conventional' => [['interest_rate_percent' => ''], 'interest_rate_percent', 'Enter the yearly interest rate you are offering.'],
            'rate negative' => [['interest_rate_percent' => '-1'], 'interest_rate_percent', 'The rate can\'t be negative.'],
            'rate above 30%' => [['interest_rate_percent' => '30.01'], 'interest_rate_percent', 'The rate must be 30% or less.'],
            'markup blank on murabahah' => [['contract_type' => 'murabahah', 'interest_rate_percent' => null, 'asset_description' => 'Delivery van'], 'markup_amount', 'Enter the fixed markup added to the asset cost.'],
            'markup above the asset cost' => [['contract_type' => 'murabahah', 'interest_rate_percent' => null, 'asset_description' => 'Delivery van', 'markup_amount' => '120000.01'], 'markup_amount', 'The markup can\'t be more than the asset cost.'],
            'asset blank on murabahah' => [['contract_type' => 'murabahah', 'interest_rate_percent' => null, 'markup_amount' => '9000'], 'asset_description', 'Describe the asset being bought, like "Refrigerated delivery van".'],
            'profit share blank on mudarabah' => [['contract_type' => 'mudarabah', 'interest_rate_percent' => null], 'investor_profit_share_percent', 'Enter the share of the profit that goes to investors.'],
            'profit share 0%' => [['contract_type' => 'mudarabah', 'interest_rate_percent' => null, 'investor_profit_share_percent' => '0'], 'investor_profit_share_percent', 'The investors\' share must be between 1% and 99%, so both sides share the profit.'],
            'profit share 100%' => [['contract_type' => 'mudarabah', 'interest_rate_percent' => null, 'investor_profit_share_percent' => '100'], 'investor_profit_share_percent', 'The investors\' share must be between 1% and 99%, so both sides share the profit.'],

            // PB-14: financial information
            'income blank' => [['annual_income' => ''], 'annual_income', 'Enter your annual income before tax.'],
            'income zero' => [['annual_income' => '0'], 'annual_income', 'Your annual income must be more than 0.'],
            'income not a number' => [['annual_income' => 'a lot'], 'annual_income', 'Enter your income as a number, like 480000.'],
            'monthly repayments blank' => [['monthly_debt_repayments' => ''], 'monthly_debt_repayments', 'Enter what you repay each month on existing loans and cards. Enter 0 if nothing.'],
            'monthly repayments negative' => [['monthly_debt_repayments' => '-50'], 'monthly_debt_repayments', 'Monthly repayments can\'t be negative. Enter 0 if nothing.'],
            'employment blank' => [['employment_years' => ''], 'employment_years', 'Choose how long you have been in your job or running the business.'],
            'employment 11 years' => [['employment_years' => '11'], 'employment_years', 'Choose how long you have been in your job or running the business.'],
            'housing blank' => [['housing' => ''], 'housing', 'Choose your housing situation.'],
            'housing not offered' => [['housing' => 'castle'], 'housing', 'Choose your housing situation from the list.'],

            // PB-14: self-declared credit history
            'open credit lines blank' => [['open_credit_lines' => ''], 'open_credit_lines', 'Enter how many loans, financing facilities and credit cards you have open. Enter 0 if none.'],
            'open credit lines negative' => [['open_credit_lines' => '-1'], 'open_credit_lines', $count],
            'open credit lines 1.5' => [['open_credit_lines' => '1.5'], 'open_credit_lines', $count],
            'late payments blank' => [['late_payments_2y' => ''], 'late_payments_2y', 'Enter how many times a repayment was 30 or more days late in the past 2 years. Enter 0 if never.'],
            'late payments 100' => [['late_payments_2y' => '100'], 'late_payments_2y', $count],
            'public records blank' => [['public_records' => ''], 'public_records', 'Enter how many court judgments, bankruptcies or written-off debts you have had. Enter 0 if none.'],
            'public records text' => [['public_records' => 'none'], 'public_records', $count],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_an_invalid_or_missing_field_blocks_submission_with_a_clear_message(array $overrides, string $field, string $message): void
    {
        $this->submit(self::validInput($overrides))
            ->assertRedirect('/borrower/campaigns/create')
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(0, Campaign::count());
        $this->assertSame(0, CampaignFinancials::count());
    }

    public function test_an_empty_form_reports_every_required_field_at_once(): void
    {
        $this->submit([])->assertSessionHasErrors([
            'title', 'purpose', 'description', 'amount_requested', 'tenure_months', 'contract_type',
            'annual_income', 'monthly_debt_repayments', 'employment_years', 'housing',
            'open_credit_lines', 'late_payments_2y', 'public_records',
        ]);

        $this->assertSame(0, Campaign::count());
    }

    /**
     * Boundary values that must be accepted.
     *
     * @return array<string, array{0: array<string, string>}>
     */
    public static function validBoundaries(): array
    {
        return [
            'minimum amount' => [['amount_requested' => '10000']],
            'maximum amount' => [['amount_requested' => '2000000']],
            'amount with 2 decimals' => [['amount_requested' => '120000.50']],
            'shortest tenure (3 months)' => [['tenure_months' => '3']],
            'longest tenure (60 months)' => [['tenure_months' => '60']],
            '0% rate' => [['interest_rate_percent' => '0']],
            '30% rate' => [['interest_rate_percent' => '30']],
            'no existing repayments' => [['monthly_debt_repayments' => '0']],
            'less than a year in the job' => [['employment_years' => '0']],
            'ten years or more in the job' => [['employment_years' => '10']],
            'family home' => [['housing' => 'family_home']],
            'all counts zero' => [['open_credit_lines' => '0', 'late_payments_2y' => '0', 'public_records' => '0']],
            'counts at 99' => [['open_credit_lines' => '99', 'late_payments_2y' => '99', 'public_records' => '99']],
            'no business background' => [['business_background' => '']],
            'profit share 1%' => [['contract_type' => 'mudarabah', 'interest_rate_percent' => null, 'investor_profit_share_percent' => '1']],
            'profit share 99%' => [['contract_type' => 'mudarabah', 'interest_rate_percent' => null, 'investor_profit_share_percent' => '99']],
            'markup equal to the asset cost' => [['contract_type' => 'murabahah', 'interest_rate_percent' => null, 'asset_description' => 'Delivery van', 'markup_amount' => '120000']],
        ];
    }

    #[DataProvider('validBoundaries')]
    public function test_boundary_values_are_accepted(array $overrides): void
    {
        $this->submit(self::validInput($overrides))->assertSessionHasNoErrors();

        $this->assertSame(1, Campaign::count());
    }

    // ---- Structured financial information (PB-14) ------------------------------------------

    public function test_the_scoring_fields_are_stored_as_structured_values(): void
    {
        $this->submit(self::validInput(['annual_income' => '500000']))->assertSessionHasNoErrors();

        $financials = Campaign::sole()->financials;
        $this->assertSame('500000.00', $financials->annual_income);
        $this->assertSame('6000.00', $financials->monthly_debt_repayments);
        $this->assertSame(7, $financials->employment_years);
        $this->assertSame('own_with_home_loan', $financials->housing);
        $this->assertSame(3, $financials->open_credit_lines);
        $this->assertSame(1, $financials->late_payments_2y);
        $this->assertSame(0, $financials->public_records);

        // The profile keeps its own figure; the request has a snapshot.
        $this->assertSame('480000.00', $this->borrower->borrowerProfile->fresh()->declared_annual_income);
    }

    public function test_submitting_writes_an_audit_entry(): void
    {
        $this->submit(self::validInput());

        $entry = AuditLog::sole();
        $this->assertSame('campaign.submitted', $entry->action);
        $this->assertSame($this->borrower->id, $entry->actor_id);
        $this->assertSame(Campaign::class, $entry->subject_type);
        $this->assertSame(Campaign::sole()->id, $entry->subject_id);
        $this->assertSame('conventional', $entry->details['contract_type']);
        $this->assertNull($entry->details['credit_report_id']);
    }

    // ---- Optional MMA credit report (PB-14) ------------------------------------------------

    public function test_a_credit_report_is_stored_privately_encrypted_and_linked_to_the_request(): void
    {
        $file = $this->pdfFile('cis-report.pdf');
        $plain = $file->get();

        $this->submit(self::validInput(['credit_report' => $file]))->assertSessionHasNoErrors();

        $campaign = Campaign::sole();
        $report = $campaign->creditReport;
        $this->assertNotNull($report);
        $this->assertSame('credit_report', $report->document_type);
        $this->assertSame($campaign->id, $report->campaign_id);
        $this->assertSame($this->borrower->id, $report->user_id);
        $this->assertSame('cis-report.pdf', $report->original_name);
        $this->assertSame('pending', $report->status);

        Storage::disk(KycDocumentStore::DISK)->assertExists($report->storage_path);
        $stored = Storage::disk(KycDocumentStore::DISK)->get($report->storage_path);
        $this->assertNotSame($plain, $stored);
        $this->assertSame($plain, Crypt::decryptString($stored));

        $this->assertSame($report->id, AuditLog::sole()->details['credit_report_id']);
    }

    public function test_a_request_without_a_credit_report_is_accepted(): void
    {
        $this->submit(self::validInput())->assertSessionHasNoErrors();

        $this->assertNull(Campaign::sole()->creditReport);
        $this->assertSame(0, KycDocument::withoutGlobalScopes()->count());
    }

    public function test_a_credit_report_of_the_wrong_type_or_size_blocks_submission(): void
    {
        $this->submit(self::validInput(['credit_report' => $this->fileWithContent('report.pdf', '<?php echo "hi";')]))
            ->assertSessionHasErrors(['credit_report' => 'Upload a PDF, JPG or PNG file.']);
        $this->submit(self::validInput(['credit_report' => $this->pdfFile('report.pdf', KycDocument::MAX_KILOBYTES * 1024 + 1)]))
            ->assertSessionHasErrors(['credit_report' => 'Files must be 5 MB or smaller.']);

        $this->assertSame(0, Campaign::count());
        $this->assertSame([], Storage::disk(KycDocumentStore::DISK)->allFiles());
    }

    public function test_a_failure_part_way_saves_nothing_and_leaves_no_file_behind(): void
    {
        // Make the audit write (the last step) fail.
        Event::listen('eloquent.creating: '.AuditLog::class, fn () => throw new RuntimeException('Audit log unavailable'));

        $this->withoutExceptionHandling();
        try {
            $this->submit(self::validInput(['credit_report' => $this->pdfFile()]));
            $this->fail('The submission should have failed.');
        } catch (RuntimeException $e) {
            $this->assertSame('Audit log unavailable', $e->getMessage());
        }

        $this->assertSame(0, Campaign::count());
        $this->assertSame(0, CampaignFinancials::count());
        $this->assertSame(0, KycDocument::count());
        $this->assertSame([], Storage::disk(KycDocumentStore::DISK)->allFiles());
    }

    // ---- Seeing your own requests ----------------------------------------------------------

    public function test_the_list_shows_only_the_borrowers_own_requests(): void
    {
        $own = Campaign::factory()->submitted()->for($this->borrower, 'borrower')->create(['title' => 'My delivery van']);
        Campaign::factory()->submitted()->create(['title' => 'Someone else\'s request']);

        $this->actingAs($this->borrower)->get('/borrower/campaigns')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Borrower/Campaigns/Index')
                ->has('campaigns', 1)
                ->where('campaigns.0.id', $own->id)
                ->where('campaigns.0.status', 'submitted')
                ->where('canCreate', true)
            );
    }

    public function test_a_borrower_can_open_their_own_request_with_its_financial_information(): void
    {
        $campaign = Campaign::factory()->submitted()->for($this->borrower, 'borrower')->create();

        $this->actingAs($this->borrower)->get("/borrower/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Borrower/Campaigns/Show')
                ->where('campaign.id', $campaign->id)
                ->where('campaign.status', 'submitted')
                ->has('finances', 4)
                ->has('creditHistory', 3)
                ->where('creditReport', null)
            );
    }

    public function test_another_borrowers_request_is_not_found(): void
    {
        $campaign = Campaign::factory()->submitted()->create();

        $this->actingAs($this->borrower)->get("/borrower/campaigns/{$campaign->id}")->assertNotFound();
    }

    public function test_the_portal_home_counts_the_borrowers_requests(): void
    {
        $this->actingAs($this->borrower)->get('/borrower')
            ->assertInertia(fn (Assert $page) => $page->where('requestCount', 0));

        Campaign::factory()->submitted()->for($this->borrower, 'borrower')->create();

        $this->actingAs($this->borrower)->get('/borrower')
            ->assertInertia(fn (Assert $page) => $page->where('requestCount', 1));
    }
}
