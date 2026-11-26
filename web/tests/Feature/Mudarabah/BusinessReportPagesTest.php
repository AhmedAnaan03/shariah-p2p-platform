<?php

namespace Tests\Feature\Mudarabah;

use App\Models\AuditLog;
use App\Models\BusinessReport;
use App\Models\Campaign;
use App\Models\User;
use App\Services\BusinessReportService;
use App\Services\WalletLedger;
use App\Support\MudarabahPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-29 through HTTP: the business's report form and its validation, the administrator's review
 * and decision (with its validation), who may reach each, and what the pages show.
 *
 * The campaign: a MVR 10,000 Mudarabah over 3 months, 60 : 40, funded 6,000 + 4,000, paid out on
 * 26 Nov 2026; period 1 is planned for 26 Dec 2026, which is "today" in these tests.
 */
class BusinessReportPagesTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    private Campaign $campaign;

    /** @var list<User> */
    private array $investors;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 10:00:00');
        Notification::fake();
        Storage::fake('kyc');
        [$this->campaign, $this->investors] = $this->paidOutCampaign('mudarabah', 10_000, 3, [6_000, 4_000]);
        app(WalletLedger::class)->topUp($this->campaign->borrower, 2_000_00);
        $this->admin = User::factory()->admin()->create();
        Carbon::setTestNow('2026-12-26 10:00:00');
    }

    private function storeUrl(): string
    {
        return "/borrower/campaigns/{$this->campaign->id}/reports";
    }

    private function report(array $cents = ['revenue' => 5_000_00, 'costs' => 3_000_00, 'capital_returned' => 3_333_33]): BusinessReport
    {
        return app(BusinessReportService::class)->submit($this->campaign->borrower, $this->campaign, $cents);
    }

    public function test_the_business_submits_a_report_with_an_optional_document(): void
    {
        $this->actingAs($this->campaign->borrower)->get("/borrower/campaigns/{$this->campaign->id}/reports/create")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Borrower/Campaigns/ReportCreate')
                ->where('period.label', 'Period 1')
                ->where('period.suggestedCapital', '3333.33')
                ->where('capital.outstanding', '10000.00')
                ->where('investorShareBp', 6000));

        $this->actingAs($this->campaign->borrower)->post($this->storeUrl(), [
            'revenue' => '5000', 'costs' => '3,000.00', 'capital_returned' => '3333.33',
            'document' => UploadedFile::fake()->createWithContent('accounts.pdf', '%PDF-1.4 accounts'),
        ])->assertRedirect("/borrower/campaigns/{$this->campaign->id}")
            ->assertSessionHas('status', 'Your report for period 1 was submitted. An administrator will review it; no money moves until it is approved.');

        $report = BusinessReport::firstOrFail();
        $this->assertSame(['2000.00', 'submitted'], [(string) $report->profit, $report->status]);
        $this->assertNotNull($report->kyc_document_id);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidReports(): array
    {
        $valid = ['revenue' => '5000', 'costs' => '3000', 'capital_returned' => '0'];

        return [
            'TC1 blank revenue' => [[...$valid, 'revenue' => ''], 'revenue', 'Enter the period’s revenue. Enter 0 if there was none.'],
            'TC2 blank costs' => [[...$valid, 'costs' => ''], 'costs', 'Enter the period’s costs. Enter 0 if there were none.'],
            'TC3 blank capital' => [[...$valid, 'capital_returned' => ''], 'capital_returned', 'Enter the capital you are returning. Enter 0 if none this period.'],
            'TC4 negative costs' => [[...$valid, 'costs' => '-5'], 'costs', 'Enter an amount in MVR, like 5000 or 5000.50. Enter 0 if there was none.'],
            'TC5 three decimals' => [[...$valid, 'revenue' => '10.123'], 'revenue', 'Enter an amount in MVR, like 5000 or 5000.50. Enter 0 if there was none.'],
            'TC6 more capital than invested' => [[...$valid, 'capital_returned' => '10000.01'], 'capital_returned', 'The capital returned can be at most the capital still invested, MVR 10,000.00.'],
        ];
    }

    #[DataProvider('invalidReports')]
    public function test_a_report_is_validated(array $data, string $field, string $message): void
    {
        $this->actingAs($this->campaign->borrower)->post($this->storeUrl(), $data)->assertSessionHasErrors([$field => $message]);
        $this->assertSame(0, BusinessReport::count());
    }

    public function test_a_document_of_the_wrong_type_is_refused(): void
    {
        $this->actingAs($this->campaign->borrower)->post($this->storeUrl(), [
            'revenue' => '1', 'costs' => '0', 'capital_returned' => '0', 'document' => UploadedFile::fake()->create('notes.docx', 10),
        ])->assertSessionHasErrors(['document' => 'The document must be a PDF, JPG or PNG file.']);
    }

    public function test_the_form_redirects_with_the_reason_before_the_planned_date(): void
    {
        Carbon::setTestNow('2026-12-20 10:00:00');

        $this->actingAs($this->campaign->borrower)->get("/borrower/campaigns/{$this->campaign->id}/reports/create")
            ->assertRedirect("/borrower/campaigns/{$this->campaign->id}")
            ->assertSessionHas('notice', 'The report for period 1 can be submitted from its planned date, 26 Dec 2026.');
    }

    public function test_only_the_campaigns_own_business_can_report(): void
    {
        $other = User::factory()->borrower()->verified()->create();
        $data = ['revenue' => '1', 'costs' => '0', 'capital_returned' => '0'];

        $this->actingAs($other)->get("/borrower/campaigns/{$this->campaign->id}/reports/create")->assertNotFound();
        $this->actingAs($other)->post($this->storeUrl(), $data)->assertNotFound();
        $this->actingAs($this->investors[0])->post($this->storeUrl(), $data)->assertForbidden();
        $this->actingAs($this->admin)->post($this->storeUrl(), $data)->assertForbidden();
        $this->assertSame(0, BusinessReport::count());
    }

    public function test_the_admin_reviews_and_approves_a_profit(): void
    {
        $report = $this->report();

        $this->actingAs($this->admin)->get('/admin/reports')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Reports/Index')->has('reports', 1)->where('reports.0.statusLabel', 'Awaiting review'));
        $this->actingAs($this->admin)->get("/admin/reports/{$report->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Reports/Show')
                ->where('isLoss', false)
                ->where('previews.approve.allowed', true)
                ->where('previews.approve.rows', [['Capital returned', '3333.33'], ['Investors’ profit share', '1200.00'], ['Business’s profit share', '800.00'], ['Capital still invested afterwards', '6666.67'], ['Paid to investors from the business’s wallet', '4533.33']]));

        $this->actingAs($this->admin)->post("/admin/reports/{$report->id}/decision", ['decision' => 'approve'])
            ->assertSessionHas('status', 'Report approved and the money shared out. The business and the investors have been told.');
        $this->assertSame('approved', $report->fresh()->status);
        $this->assertLedgerReconciles();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool, 2: string, 3: string}>
     */
    public static function invalidDecisions(): array
    {
        return [
            'TC7 no decision' => [[], false, 'decision', 'Choose a decision.'],
            'TC8 unknown decision' => [['decision' => 'maybe'], false, 'decision', 'Choose a decision from the list.'],
            'TC9 reject without a reason' => [['decision' => 'reject'], false, 'reason', 'Give a reason. The business will see it, and it is recorded in the audit log.'],
            'TC10 reject with a short reason' => [['decision' => 'reject', 'reason' => 'Wrong.'], false, 'reason', 'Write at least 10 characters so the business knows why.'],
            'TC11 a loss approved without a cause' => [['decision' => 'approve'], true, 'loss_cause', 'Choose whether this was a genuine business loss or caused by negligence or misconduct.'],
            'TC12 an unknown cause' => [['decision' => 'approve', 'loss_cause' => 'weather'], true, 'loss_cause', 'Choose a cause from the list.'],
            'TC13 negligence without a reason' => [['decision' => 'approve', 'loss_cause' => 'negligence'], true, 'reason', 'Give a reason. The business will see it, and it is recorded in the audit log.'],
        ];
    }

    #[DataProvider('invalidDecisions')]
    public function test_a_decision_is_validated(array $data, bool $loss, string $field, string $message): void
    {
        $report = $this->report($loss ? ['revenue' => 1_000_00, 'costs' => 2_000_00, 'capital_returned' => 0] : ['revenue' => 5_000_00, 'costs' => 3_000_00, 'capital_returned' => 0]);

        $this->actingAs($this->admin)->post("/admin/reports/{$report->id}/decision", $data)->assertSessionHasErrors([$field => $message]);
        $this->assertSame('submitted', $report->fresh()->status);
    }

    public function test_a_negligence_finding_is_recorded_with_its_reason_and_audit_logged(): void
    {
        $report = $this->report(['revenue' => 1_000_00, 'costs' => 2_000_00, 'capital_returned' => 0]);
        $reason = 'Stock was sold below cost to a related party.';

        $this->actingAs($this->admin)->post("/admin/reports/{$report->id}/decision", ['decision' => 'approve', 'loss_cause' => 'negligence', 'reason' => $reason])->assertSessionHasNoErrors();

        $this->assertSame(['negligence', $reason, '1000.00'], [$report->fresh()->loss_cause, $report->fresh()->decision_reason, (string) $report->fresh()->business_liable_loss]);
        $this->assertSame($reason, AuditLog::where('action', 'business_report.approved')->latest('id')->value('details')['reason']);
    }

    public function test_approval_the_business_cannot_pay_for_is_refused_with_its_balance(): void
    {
        $report = $this->report();
        app(WalletLedger::class)->withdraw($this->campaign->borrower, $this->balance($this->campaign->borrower) - 50_00);

        $this->actingAs($this->admin)->post("/admin/reports/{$report->id}/decision", ['decision' => 'approve'])
            ->assertSessionHasErrors(['decision' => 'The business’s wallet holds only MVR 50.00, not enough for what this report pays the investors. Ask the business to top up, or reject the report with a reason.']);
        $this->assertSame('submitted', $report->fresh()->status);
    }

    public function test_only_administrators_review_reports_and_open_their_documents(): void
    {
        $report = app(BusinessReportService::class)->submit($this->campaign->borrower, $this->campaign, ['revenue' => 1_00, 'costs' => 0, 'capital_returned' => 0], UploadedFile::fake()->createWithContent('a.pdf', '%PDF-1.4 x'));

        foreach ([$this->campaign->borrower, $this->investors[0]] as $user) {
            $this->actingAs($user)->get('/admin/reports')->assertForbidden();
            $this->actingAs($user)->get("/admin/reports/{$report->id}")->assertForbidden();
            $this->actingAs($user)->post("/admin/reports/{$report->id}/decision", ['decision' => 'approve'])->assertForbidden();
        }
        // The document: the business and administrators only; an investor gets 404.
        $this->actingAs($this->investors[0])->get("/documents/{$report->kyc_document_id}")->assertNotFound();
        $this->actingAs($this->admin)->get("/documents/{$report->kyc_document_id}")->assertOk();
        $this->actingAs($this->campaign->borrower)->get("/documents/{$report->kyc_document_id}")->assertOk();
        $this->assertSame('submitted', $report->fresh()->status);
    }

    public function test_the_admin_list_filters_by_status_and_searches_the_campaign(): void
    {
        $report = $this->report();
        app(BusinessReportService::class)->reject($this->admin, $report, 'The costs do not match the accounts.');
        $this->report();

        $this->actingAs($this->admin)->get('/admin/reports?status=rejected')
            ->assertInertia(fn (Assert $page) => $page->has('reports', 1)->where('reports.0.statusLabel', 'Not accepted'));
        $this->actingAs($this->admin)->get('/admin/reports?search=nothing-like-this')
            ->assertInertia(fn (Assert $page) => $page->has('reports', 0)->where('hasAny', true));
        $this->actingAs($this->admin)->get('/admin/reports?search='.urlencode($this->campaign->title))
            ->assertInertia(fn (Assert $page) => $page->has('reports', 2)->where('reports.0.status', 'submitted'));
    }

    public function test_the_business_and_investors_see_the_capital_account_and_each_report(): void
    {
        $this->actingAs($this->admin);
        app(BusinessReportService::class)->approve($this->admin, $this->report(['revenue' => 1_000_00, 'costs' => 2_000_00, 'capital_returned' => 0]), 'business');

        $this->actingAs($this->campaign->borrower)->get("/borrower/campaigns/{$this->campaign->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('repayment', null)
                ->where('mudarabah.account.3', ['key' => 'loss', 'label' => 'Genuine losses borne', 'value' => '1000.00'])
                ->where('mudarabah.reports.0.causeLabel', 'Genuine business loss')
                ->where('schedule.rows.0.stateLabel', 'Report approved'));

        $this->actingAs($this->investors[0])->get("/investor/campaigns/{$this->campaign->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('mudarabah.account.3.value', '600.00')
                ->where('mudarabah.account.6', ['key' => 'outstanding', 'label' => 'Capital still invested', 'value' => '5400.00'])
                ->where('mudarabah.reports.0.yours.loss', '600.00')
                ->missing('mudarabah.reports.0.document')
                ->where('schedule.rows.0.lossBorne', '600.00'));
        $this->assertSame(MudarabahPresenter::RULES_NOTE, MudarabahPresenter::forBorrower($this->campaign, $this->campaign->borrower)['note']);
    }

    public function test_a_period_with_a_report_waiting_shows_it_on_the_schedule(): void
    {
        $this->report();

        $this->actingAs($this->campaign->borrower)->get("/borrower/campaigns/{$this->campaign->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('schedule.rows.0.state', 'review')
                ->where('schedule.rows.0.stateLabel', 'Report submitted, awaiting review')
                ->where('mudarabah.next.allowed', false)
                ->where('mudarabah.next.reason', 'The report for period 1 is waiting for review.'));
    }
}
