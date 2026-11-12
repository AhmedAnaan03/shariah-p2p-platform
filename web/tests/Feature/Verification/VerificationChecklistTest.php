<?php

namespace Tests\Feature\Verification;

use App\Models\BorrowerProfile;
use App\Models\InvestorProfile;
use App\Models\KycDocument;
use App\Models\User;
use App\Support\VerificationChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PB-09 to PB-11: what a user must provide before an administrator reviews them.
 */
class VerificationChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_borrower_has_no_profile_and_needs_an_id_document(): void
    {
        $checklist = VerificationChecklist::for(User::factory()->borrower()->create());

        $this->assertFalse($checklist->hasProfile());
        $this->assertSame(['national_id'], $checklist->missingDocumentTypes());
        $this->assertFalse($checklist->isReadyForReview());
    }

    public function test_an_individual_borrower_needs_only_a_national_id(): void
    {
        $borrower = BorrowerProfile::factory()->individual()->create()->user;
        KycDocument::factory()->for($borrower)->ofType('national_id')->create();

        $checklist = VerificationChecklist::for($borrower);

        $this->assertSame(['national_id'], $checklist->requiredDocumentTypes());
        $this->assertTrue($checklist->isReadyForReview());
    }

    public function test_an_sme_also_needs_its_business_registration(): void
    {
        $borrower = BorrowerProfile::factory()->create()->user; // SME by default
        KycDocument::factory()->for($borrower)->ofType('national_id')->create();

        $checklist = VerificationChecklist::for($borrower);

        $this->assertSame(['business_registration'], $checklist->missingDocumentTypes());
        $this->assertFalse($checklist->isReadyForReview());

        KycDocument::factory()->for($borrower)->ofType('business_registration')->create();
        $this->assertTrue(VerificationChecklist::for($borrower->fresh())->isReadyForReview());
    }

    public function test_a_rejected_document_does_not_count(): void
    {
        $borrower = BorrowerProfile::factory()->individual()->create()->user;
        KycDocument::factory()->for($borrower)->ofType('national_id')->rejected()->create();

        $this->assertSame(['national_id'], VerificationChecklist::for($borrower)->missingDocumentTypes());
    }

    public function test_an_investor_needs_a_profile_and_a_national_id(): void
    {
        $investor = InvestorProfile::factory()->create()->user;
        $this->assertSame(['national_id'], VerificationChecklist::for($investor)->missingDocumentTypes());

        KycDocument::factory()->for($investor)->ofType('national_id')->create();
        $this->assertTrue(VerificationChecklist::for($investor->fresh())->isReadyForReview());
    }

    public function test_a_complete_user_who_is_not_pending_is_not_ready_for_review(): void
    {
        $borrower = BorrowerProfile::factory()->individual()->create()->user;
        KycDocument::factory()->for($borrower)->ofType('national_id')->create();
        $borrower->forceFill(['status' => 'verified'])->save();

        $checklist = VerificationChecklist::for($borrower->fresh());

        $this->assertTrue($checklist->isComplete());
        $this->assertFalse($checklist->isReadyForReview());
    }
}
