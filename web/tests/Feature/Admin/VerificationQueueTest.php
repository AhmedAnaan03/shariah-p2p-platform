<?php

namespace Tests\Feature\Admin;

use App\Models\BorrowerProfile;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesOnboardedUsers;
use Tests\TestCase;

/**
 * PB-11: the admin sees a queue of pending users with their documents. Since PB-53 an account
 * is listed once it has finished and submitted every onboarding step.
 */
class VerificationQueueTest extends TestCase
{
    use CreatesOnboardedUsers, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function readyBorrower(array $attributes = []): User
    {
        return $this->onboardedBorrower($attributes);
    }

    private function readyInvestor(array $attributes = []): User
    {
        return $this->onboardedInvestor($attributes);
    }

    public function test_the_queue_lists_ready_borrowers_and_investors_oldest_first(): void
    {
        $newer = $this->readyBorrower(['name' => 'Newer Borrower', 'created_at' => Carbon::parse('2026-11-10')]);
        $older = $this->readyInvestor(['name' => 'Older Investor', 'created_at' => Carbon::parse('2026-11-01')]);

        $this->actingAs($this->admin)->get('/admin/verifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Verifications/Index')
                ->has('applicants', 2)
                ->where('applicants.0.name', 'Older Investor')
                ->where('applicants.0.role', 'investor')
                ->where('applicants.0.role_label', 'Investor')
                ->where('applicants.0.documents_count', 2)
                ->where('applicants.0.url', route('admin.verifications.show', $older))
                ->where('applicants.1.name', 'Newer Borrower')
                ->where('applicants.1.url', route('admin.verifications.show', $newer))
                ->where('incompleteCount', 0)
            );
    }

    public function test_incomplete_decided_and_admin_accounts_are_not_in_the_queue(): void
    {
        $this->readyBorrower(['name' => 'Ready']);
        User::factory()->borrower()->create(['name' => 'No profile yet']);
        $smeWithoutRegistration = User::factory()->borrower()->submitted()->create(['name' => 'SME missing registration']);
        BorrowerProfile::factory()->for($smeWithoutRegistration)->create();
        $this->fillSlots($smeWithoutRegistration, except: ['business_registration']);
        $this->onboardedBorrower(['name' => 'Complete but not submitted'], submitted: false);
        $this->readyBorrower(['name' => 'Already verified', 'status' => 'verified']);
        $this->readyBorrower(['name' => 'Rejected, not changed since', 'status' => 'rejected']);

        $this->actingAs($this->admin)->get('/admin/verifications')
            ->assertInertia(fn (Assert $page) => $page
                ->has('applicants', 1)
                ->where('applicants.0.name', 'Ready')
                ->where('incompleteCount', 3) // the pending accounts not yet complete and submitted
            );
    }

    public function test_an_account_whose_only_id_document_was_rejected_is_not_ready(): void
    {
        $borrower = $this->readyBorrower();
        $borrower->kycDocuments()->where('document_type', 'national_id')->update(['status' => 'rejected']);

        $this->actingAs($this->admin)->get('/admin/verifications')
            ->assertInertia(fn (Assert $page) => $page->has('applicants', 0)->where('incompleteCount', 1));
    }

    public function test_the_admin_portal_shows_how_many_accounts_are_ready(): void
    {
        $this->readyBorrower();
        $this->readyInvestor();
        User::factory()->borrower()->create(); // incomplete

        $this->actingAs($this->admin)->get('/admin')
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Portal')->where('readyForReview', 2));
    }

    public function test_the_review_page_shows_the_profile_and_documents(): void
    {
        $borrower = User::factory()->borrower()->submitted()->create(['name' => 'Ahmed Rasheed']);
        BorrowerProfile::factory()->for($borrower)->create([
            'national_id_no' => 'A111222',
            'business_name' => 'Island Bakes',
            'sector' => 'food_and_beverage',
            'declared_annual_income' => '480000.00',
            'address_house' => 'H. Coral Villa',
            'address_road' => 'Majeedhee Magu',
            'postcode' => '20026',
        ]);
        $id = KycDocument::factory()->for($borrower)->ofType('national_id')->create();
        $registration = KycDocument::factory()->for($borrower)->ofType('business_registration')->create();
        $this->fillSlots($borrower, except: ['national_id', 'business_registration']);

        $this->actingAs($this->admin)->get("/admin/verifications/{$borrower->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Verifications/Show')
                ->where('applicant.name', 'Ahmed Rasheed')
                ->where('applicant.status', 'pending_verification')
                ->where('profile', fn ($profile) => collect($profile)->pluck('value', 'label')->only([
                    'Borrowing as', 'National ID card number', 'Address', 'Business name', 'Sector', 'Declared annual revenue',
                ])->all() === [
                    'Borrowing as' => 'Business (SME)',
                    'National ID card number' => 'A111222', // decrypted for the admin to compare
                    'Address' => 'H. Coral Villa, Majeedhee Magu, Malé, Malé City 20026',
                    'Business name' => 'Island Bakes',
                    'Sector' => 'Food and beverage',
                    'Declared annual revenue' => 'MVR 480,000.00',
                ])
                ->has('documents', 4)
                ->where('documents.2.url', route('documents.show', $registration))
                ->where('documents.3.url', route('documents.show', $id))
                ->where('missingDocuments', [])
                ->where('missingSteps', [])
                ->where('canDecide', true)
                ->where('canApprove', true)
            );
    }

    public function test_the_review_page_lists_missing_documents_and_blocks_approval(): void
    {
        $borrower = BorrowerProfile::factory()->create()->user; // SME, no documents

        $this->actingAs($this->admin)->get("/admin/verifications/{$borrower->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('missingDocuments', ['National ID card', 'Business registration certificate', 'Proof of business address', 'Financial statement'])
                ->where('missingSteps', ['The account has not been submitted for verification.'])
                ->where('canApprove', false)
            );
    }

    public function test_an_admin_account_cannot_be_opened_for_review(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($this->admin)->get("/admin/verifications/{$otherAdmin->id}")->assertNotFound();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function adminOnlyRequests(): array
    {
        return [
            'queue' => ['get', '/admin/verifications'],
            'review page' => ['get', '/admin/verifications/{id}'],
            'decision' => ['post', '/admin/verifications/{id}/decision'],
        ];
    }

    #[DataProvider('adminOnlyRequests')]
    public function test_borrowers_and_investors_cannot_use_the_verification_pages(string $method, string $url): void
    {
        $applicant = $this->readyBorrower();
        $url = str_replace('{id}', (string) $applicant->id, $url);

        foreach (['borrower', 'investor'] as $role) {
            $this->actingAs(User::factory()->{$role}()->verified()->create())
                ->{$method}($url, ['decision' => 'approve'])
                ->assertForbidden();
        }

        $this->assertSame('pending_verification', $applicant->fresh()->status);
    }

    #[DataProvider('adminOnlyRequests')]
    public function test_guests_are_sent_to_sign_in(string $method, string $url): void
    {
        $applicant = $this->readyBorrower();

        $this->{$method}(str_replace('{id}', (string) $applicant->id, $url))->assertRedirect('/login');
    }
}
