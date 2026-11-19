<?php

namespace Tests\Feature\Onboarding;

use App\Models\AuditLog;
use App\Models\BorrowerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOnboardedUsers;
use Tests\TestCase;

/**
 * PB-53: the review step and "Submit for verification", which is what puts an account in the
 * admin queue. A rejected account fixes its details and submits again.
 */
class OnboardingSubmitTest extends TestCase
{
    use CreatesOnboardedUsers, RefreshDatabase;

    public function test_the_review_step_shows_what_the_administrator_will_check(): void
    {
        $borrower = $this->onboardedBorrower(submitted: false);

        $this->actingAs($borrower)->get('/borrower/onboarding/review')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Review')
                ->where('complete', true)
                ->where('submittedAt', null)
                ->has('documents', 2)
                ->where('details', fn ($details) => collect($details)->pluck('label')->contains('Address'))
                ->where('editLinks.address', route('borrower.onboarding.show', 'address'))
                ->where('submitUrl', route('borrower.onboarding.submit'))
            );
    }

    public function test_submitting_a_complete_account_sends_it_for_verification(): void
    {
        Carbon::setTestNow('2026-11-19 09:00:00');
        $borrower = $this->onboardedBorrower(submitted: false);

        $this->actingAs($borrower)->post('/borrower/onboarding/submit')
            ->assertRedirect('/borrower/onboarding/review')
            ->assertSessionHas('status', 'Submitted. An administrator will check your details and documents, usually within two working days.');

        $this->assertTrue($borrower->fresh()->verification_submitted_at->equalTo(now()));
        $entry = AuditLog::sole();
        $this->assertSame('user.submitted_for_verification', $entry->action);
        $this->assertSame(['resubmitted_after_rejection' => false], $entry->details);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/verifications')
            ->assertInertia(fn (Assert $page) => $page->has('applicants', 1)->where('applicants.0.id', $borrower->id));
    }

    public function test_an_incomplete_account_cannot_be_submitted(): void
    {
        $borrower = User::factory()->borrower()->create();
        BorrowerProfile::factory()->individual()->for($borrower)->create(); // no documents

        $this->actingAs($borrower)->post('/borrower/onboarding/submit')
            ->assertRedirect('/borrower/onboarding/documents')
            ->assertSessionHasErrors(['submit' => 'Finish this step before you submit your account for verification.']);

        $this->assertNull($borrower->fresh()->verification_submitted_at);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_an_account_with_nothing_saved_cannot_be_submitted(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->actingAs($borrower)->post('/borrower/onboarding/submit')->assertRedirect('/borrower/onboarding/profile');

        $this->assertNull($borrower->fresh()->verification_submitted_at);
    }

    public function test_an_investor_must_accept_the_risk_acknowledgement_before_submitting(): void
    {
        $investor = $this->onboardedInvestor(submitted: false);
        $investor->investorProfile->forceFill(['risk_acknowledged_at' => null])->save();

        $this->actingAs($investor)->post('/investor/onboarding/submit')->assertRedirect('/investor/onboarding/risk');

        $this->assertNull($investor->fresh()->verification_submitted_at);
    }

    public function test_a_rejected_account_goes_back_to_the_queue_only_when_submitted_again(): void
    {
        $borrower = $this->onboardedBorrower(['status' => 'rejected', 'status_reason' => 'ID is blurred.', 'verification_submitted_at' => null]);

        $this->actingAs($borrower)->post('/borrower/onboarding/submit')->assertSessionHasNoErrors();

        $borrower->refresh();
        $this->assertSame('pending_verification', $borrower->status);
        $this->assertNull($borrower->status_reason);
        $this->assertNotNull($borrower->verification_submitted_at);
        $this->assertSame(['resubmitted_after_rejection' => true], AuditLog::sole()->details);
    }

    public function test_after_submitting_the_review_step_shows_the_account_is_waiting(): void
    {
        $borrower = $this->onboardedBorrower();

        $this->actingAs($borrower)->get('/borrower/onboarding')->assertRedirect('/borrower/onboarding/review');
        $this->actingAs($borrower)->get('/borrower/onboarding/review')
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'pending_verification')
                ->whereNot('submittedAt', null)
                ->where('wizard.steps.3.done', true)
            );
    }
}
