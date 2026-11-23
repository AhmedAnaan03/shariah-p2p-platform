<?php

namespace Tests\Feature\Verification;

use App\Models\BorrowerProfile;
use App\Models\InvestorProfile;
use App\Models\KycDocument;
use App\Models\User;
use App\Support\VerificationChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesOnboardedUsers;
use Tests\TestCase;

/**
 * PB-09 to PB-11, extended by PB-53: what an account needs before an administrator can review
 * it. A profile, a complete address, every document slot filled,
 * the risk acknowledgement for investors, and the explicit submission.
 */
class VerificationChecklistTest extends TestCase
{
    use CreatesOnboardedUsers, RefreshDatabase;

    public function test_a_new_borrower_has_no_profile_and_needs_the_individual_documents(): void
    {
        $checklist = VerificationChecklist::for(User::factory()->borrower()->create());

        $this->assertFalse($checklist->hasProfile());
        $this->assertSame(['national_id', 'proof_of_address', 'salary_slip'], $checklist->missingDocumentTypes());
        $this->assertFalse($checklist->isReadyForReview());
    }

    public function test_an_individual_borrower_needs_an_id_card_proof_of_address_and_salary_slip(): void
    {
        $borrower = $this->onboardedBorrower();

        $checklist = VerificationChecklist::for($borrower);

        $this->assertSame(['national_id', 'proof_of_address', 'salary_slip'], $checklist->requiredDocumentTypes());
        $this->assertTrue($checklist->isReadyForReview());
    }

    public function test_an_sme_needs_all_four_business_slots(): void
    {
        $borrower = User::factory()->borrower()->submitted()->create();
        BorrowerProfile::factory()->for($borrower)->create(); // SME by default
        $this->fillSlots($borrower, except: ['business_registration', 'financial_statement']);

        $checklist = VerificationChecklist::for($borrower);

        $this->assertSame(['national_id', 'business_registration', 'proof_of_address', 'financial_statement'], $checklist->requiredDocumentTypes());
        $this->assertSame(['business_registration', 'financial_statement'], $checklist->missingDocumentTypes());
        $this->assertFalse($checklist->isReadyForReview());

        $this->fillSlots($borrower, except: ['national_id', 'proof_of_address']);
        $this->assertTrue(VerificationChecklist::for($borrower->fresh())->isReadyForReview());
    }

    public function test_a_rejected_document_does_not_count(): void
    {
        $borrower = $this->onboardedBorrower();
        $borrower->kycDocuments()->where('document_type', 'national_id')->update(['status' => 'rejected']);

        $this->assertSame(['national_id'], VerificationChecklist::for($borrower->fresh())->missingDocumentTypes());
    }

    public function test_a_replaced_document_does_not_count_but_its_replacement_does(): void
    {
        $borrower = $this->onboardedBorrower();
        $borrower->kycDocuments()->where('document_type', 'national_id')->update(['replaced_at' => now()]);

        $this->assertSame(['national_id'], VerificationChecklist::for($borrower->fresh())->missingDocumentTypes());

        KycDocument::factory()->for($borrower)->ofType('national_id')->create();
        $this->assertSame([], VerificationChecklist::for($borrower->fresh())->missingDocumentTypes());
    }

    public function test_an_investor_also_needs_the_risk_acknowledgement(): void
    {
        $investor = User::factory()->investor()->submitted()->create();
        InvestorProfile::factory()->for($investor)->create();
        $this->fillSlots($investor);

        $checklist = VerificationChecklist::for($investor);
        $this->assertTrue($checklist->hasAllDocuments());
        $this->assertFalse($checklist->hasAcceptedRisk());
        $this->assertFalse($checklist->isComplete());

        $investor->investorProfile->forceFill(['risk_acknowledged_at' => now()])->save();
        $this->assertTrue(VerificationChecklist::for($investor->fresh())->isReadyForReview());
    }

    /**
     * Address states: [profile factory state, complete?]
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function addressStates(): array
    {
        return [
            'no address yet (blank)' => ['withoutAddress', false],
            'every field filled and checked' => ['individual', true],
        ];
    }

    #[DataProvider('addressStates')]
    public function test_the_address_counts_only_when_complete(string $state, bool $complete): void
    {
        $borrower = User::factory()->borrower()->submitted()->create();
        BorrowerProfile::factory()->individual()->{$state}()->for($borrower)->create();
        $this->fillSlots($borrower);

        $checklist = VerificationChecklist::for($borrower);

        $this->assertSame($complete, $checklist->hasCompleteAddress());
        $this->assertSame($complete, $checklist->isReadyForReview());
    }

    public function test_a_complete_account_is_not_ready_until_it_is_submitted(): void
    {
        $borrower = $this->onboardedBorrower(submitted: false);

        $checklist = VerificationChecklist::for($borrower);

        $this->assertTrue($checklist->isComplete());
        $this->assertFalse($checklist->isSubmitted());
        $this->assertFalse($checklist->isReadyForReview());
    }

    public function test_a_complete_user_who_is_not_pending_is_not_ready_for_review(): void
    {
        $borrower = $this->onboardedBorrower(['status' => 'verified']);

        $checklist = VerificationChecklist::for($borrower);

        $this->assertTrue($checklist->isComplete());
        $this->assertFalse($checklist->isReadyForReview());
    }
}
