<?php

namespace Tests\Feature\Kyc;

use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Models\KycDocument;
use App\Models\User;
use App\Services\KycDocumentStore;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsDatabaseConstraints;
use Tests\Concerns\MakesKycFiles;
use Tests\TestCase;

/**
 * PB-14: the MMA credit report uses the existing private document storage (PB-09), but it
 * belongs to a financing request, not to account verification. These tests make sure the two
 * never mix: a report is not a KYC document, doesn't count towards verification, and is not
 * decided by an account approval or rejection.
 */
class CreditReportTest extends TestCase
{
    use AssertsDatabaseConstraints, MakesKycFiles, RefreshDatabase;

    private User $borrower;

    private Campaign $campaign;

    private KycDocument $report;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(KycDocumentStore::DISK);

        $this->borrower = BorrowerProfile::factory()->individual()->create()->user;
        $this->campaign = Campaign::factory()->submitted()->for($this->borrower, 'borrower')->create();
        $this->report = app(KycDocumentStore::class)->store($this->borrower, $this->pdfFile('cis.pdf'), KycDocument::TYPE_CREDIT_REPORT, $this->campaign);
    }

    public function test_the_owner_and_administrators_can_open_the_report_and_others_get_404(): void
    {
        $url = "/documents/{$this->report->id}";

        $this->actingAs($this->borrower)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
        $this->actingAs(User::factory()->borrower()->verified()->create())->get($url)->assertNotFound();
        $this->actingAs(User::factory()->investor()->verified()->create())->get($url)->assertNotFound();
    }

    public function test_the_report_is_not_listed_on_the_kyc_documents_page(): void
    {
        $id = KycDocument::factory()->for($this->borrower)->ofType('national_id')->create()->id;

        $this->actingAs($this->borrower)->get('/borrower/documents')
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents', 1)
                ->where('documents.0.id', $id)
            );
    }

    public function test_the_report_is_not_counted_or_shown_in_account_verification(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get("/admin/verifications/{$this->borrower->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents', 0)
                ->where('missingDocuments', ['National ID card'])
            );
    }

    public function test_approving_or_rejecting_the_account_leaves_the_report_for_the_campaign_review(): void
    {
        Notification::fake();
        KycDocument::factory()->for($this->borrower)->ofType('national_id')->create();
        $admin = User::factory()->admin()->create();

        app(VerificationService::class)->reject($this->borrower, $admin, 'The ID card photo is blurred.');

        $report = $this->report->fresh();
        $this->assertSame('pending', $report->status);
        $this->assertNull($report->reviewed_by);
        $this->assertNull($report->rejection_reason);
    }

    public function test_the_database_keeps_reports_and_identity_documents_apart(): void
    {
        // A credit report must belong to a request ...
        $this->assertCheckViolated('kyc_documents_campaign_link_check', fn () => KycDocument::factory()
            ->for($this->borrower)->ofType(KycDocument::TYPE_CREDIT_REPORT)->create());

        // ... and an identity document never does.
        $this->assertCheckViolated('kyc_documents_campaign_link_check', fn () => KycDocument::factory()
            ->for($this->borrower)->ofType('national_id')->create(['campaign_id' => $this->campaign->id]));
    }

    public function test_a_credit_report_cannot_be_uploaded_on_the_kyc_documents_page(): void
    {
        $pending = User::factory()->borrower()->create();

        $this->actingAs($pending)->from('/borrower/documents')
            ->post('/borrower/documents', ['document_type' => KycDocument::TYPE_CREDIT_REPORT, 'file' => $this->pdfFile()])
            ->assertSessionHasErrors(['document_type' => 'Choose what kind of document this is from the list.']);
    }
}
