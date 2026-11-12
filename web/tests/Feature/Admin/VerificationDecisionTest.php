<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\BorrowerProfile;
use App\Models\InvestorProfile;
use App\Models\KycDocument;
use App\Models\User;
use App\Notifications\VerificationDecided;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * PB-11: approve / reject with a reason; the user is notified; each decision is written to
 * the audit log.
 */
class VerificationDecisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $applicant;

    private KycDocument $document;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 14:00:00');

        $this->admin = User::factory()->admin()->create();
        $this->applicant = BorrowerProfile::factory()->individual()->create()->user;
        $this->document = KycDocument::factory()->for($this->applicant)->ofType('national_id')->create();
    }

    private function decide(array $input, ?User $applicant = null)
    {
        $applicant ??= $this->applicant;

        return $this->actingAs($this->admin)
            ->from("/admin/verifications/{$applicant->id}")
            ->post("/admin/verifications/{$applicant->id}/decision", $input);
    }

    public function test_approving_verifies_the_user_and_their_documents(): void
    {
        Notification::fake();

        $this->decide(['decision' => 'approve'])
            ->assertRedirect('/admin/verifications')
            ->assertSessionHas('status', "{$this->applicant->name} has been verified and notified.");

        $this->assertSame('verified', $this->applicant->fresh()->status);
        $this->assertNull($this->applicant->fresh()->status_reason);

        $document = $this->document->fresh();
        $this->assertSame('approved', $document->status);
        $this->assertSame($this->admin->id, $document->reviewed_by);
        $this->assertTrue($document->reviewed_at->equalTo(now()));
        $this->assertNull($document->rejection_reason);
    }

    public function test_approving_writes_an_audit_entry(): void
    {
        Notification::fake();

        $this->decide(['decision' => 'approve']);

        $entry = AuditLog::sole();
        $this->assertSame('user.verified', $entry->action);
        $this->assertSame($this->admin->id, $entry->actor_id);
        $this->assertSame(User::class, $entry->subject_type);
        $this->assertSame($this->applicant->id, $entry->subject_id);
        // assertEquals, not assertSame: MySQL's JSON type stores object keys sorted, so the
        // order can differ from how they were written; the contents must match exactly.
        $this->assertEquals([
            'reason' => null,
            'status_before' => 'pending_verification',
            'status_after' => 'verified',
            'document_ids' => [$this->document->id],
        ], $entry->details);
        $this->assertSame('127.0.0.1', $entry->ip_address);
        $this->assertTrue($entry->created_at->equalTo(now()));
    }

    public function test_approving_notifies_the_user(): void
    {
        Notification::fake();

        $this->decide(['decision' => 'approve']);

        Notification::assertSentTo($this->applicant, VerificationDecided::class,
            fn (VerificationDecided $notification, array $channels) => $notification->approved
                && $notification->reason === null
                && $channels === ['database', 'mail']);
        Notification::assertCount(1);
    }

    public function test_rejecting_with_a_reason_rejects_the_user_and_their_documents(): void
    {
        Notification::fake();
        $reason = 'The ID card photo is too blurred to read.';

        $this->decide(['decision' => 'reject', 'reason' => $reason])
            ->assertRedirect('/admin/verifications')
            ->assertSessionHas('status', "{$this->applicant->name} has been rejected and notified of the reason.");

        $this->assertSame('rejected', $this->applicant->fresh()->status);
        $this->assertSame($reason, $this->applicant->fresh()->status_reason);
        $this->assertSame('rejected', $this->document->fresh()->status);
        $this->assertSame($reason, $this->document->fresh()->rejection_reason);
        $this->assertSame($this->admin->id, $this->document->fresh()->reviewed_by);

        $entry = AuditLog::sole();
        $this->assertSame('user.rejected', $entry->action);
        $this->assertSame($reason, $entry->details['reason']);
        $this->assertSame('rejected', $entry->details['status_after']);

        Notification::assertSentTo($this->applicant, VerificationDecided::class,
            fn (VerificationDecided $notification) => ! $notification->approved && $notification->reason === $reason);
    }

    public function test_the_rejected_user_sees_the_reason_in_their_portal(): void
    {
        Notification::fake();
        $this->decide(['decision' => 'reject', 'reason' => 'The ID card photo is too blurred to read.']);

        $this->actingAs($this->applicant->fresh())->get('/borrower')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.status', 'rejected')
                ->where('auth.user.status_reason', 'The ID card photo is too blurred to read.')
            );
    }

    public function test_the_notification_is_stored_and_emailed(): void
    {
        // No fake: the real database and mail channels run (mail goes to the test 'array' mailer).
        $this->decide(['decision' => 'reject', 'reason' => 'Your address does not match the document.']);

        $stored = $this->applicant->fresh()->notifications()->sole();
        $this->assertSame(VerificationDecided::class, $stored->type);
        $this->assertEquals([
            'decision' => 'rejected',
            'reason' => 'Your address does not match the document.',
            'message' => 'Your account verification was not approved.',
        ], $stored->data);

        $mail = (new VerificationDecided(false, 'Your address does not match the document.'))->toMail($this->applicant);
        $this->assertSame('Your account verification was not approved', $mail->subject);
        $this->assertContains('Reason: Your address does not match the document.', $mail->introLines);
    }

    /**
     * Blank and invalid decisions: [input, field, message].
     *
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     */
    public static function invalidDecisions(): array
    {
        return [
            'no decision' => [[], 'decision', 'Choose whether to approve or reject this account.'],
            'unknown decision' => [['decision' => 'maybe'], 'decision', 'Choose whether to approve or reject this account.'],
            'reject without a reason' => [['decision' => 'reject'], 'reason', 'Give a reason for the rejection. The user will see it.'],
            'reject with a blank reason' => [['decision' => 'reject', 'reason' => '   '], 'reason', 'Give a reason for the rejection. The user will see it.'],
            'reason under 10 characters' => [['decision' => 'reject', 'reason' => 'Bad ID'], 'reason', 'Write at least 10 characters so the user knows what to fix.'],
            'reason over 255 characters' => [['decision' => 'reject', 'reason' => str_repeat('x', 256)], 'reason', 'The reason must be 255 characters or fewer.'],
        ];
    }

    #[DataProvider('invalidDecisions')]
    public function test_an_invalid_decision_changes_nothing(array $input, string $field, string $message): void
    {
        Notification::fake();

        $this->decide($input)
            ->assertRedirect("/admin/verifications/{$this->applicant->id}")
            ->assertSessionHasErrors([$field => $message]);

        $this->assertNothingChanged();
        Notification::assertNothingSent();
    }

    public function test_a_reason_sent_with_an_approval_is_ignored(): void
    {
        Notification::fake();

        $this->decide(['decision' => 'approve', 'reason' => 'Looks fine to me, approving.'])->assertSessionHasNoErrors();

        $this->assertNull($this->applicant->fresh()->status_reason);
    }

    public function test_an_account_cannot_be_decided_twice(): void
    {
        Notification::fake();
        $this->decide(['decision' => 'approve']);

        $this->decide(['decision' => 'reject', 'reason' => 'Changed my mind about this one.'])
            ->assertSessionHasErrors(['decision' => 'This account has already been decided, so it is no longer waiting for review.']);

        $this->assertSame('verified', $this->applicant->fresh()->status);
        $this->assertSame(1, AuditLog::count());
        Notification::assertCount(1);
    }

    public function test_an_incomplete_account_cannot_be_approved_but_can_be_rejected(): void
    {
        Notification::fake();
        $incomplete = User::factory()->borrower()->create(); // no profile, no documents

        $this->decide(['decision' => 'approve'], $incomplete)
            ->assertSessionHasErrors(['decision' => 'This account can\'t be approved yet: its profile or required documents are missing.']);
        $this->assertSame('pending_verification', $incomplete->fresh()->status);

        $this->decide(['decision' => 'reject', 'reason' => 'The registration looks like a duplicate.'], $incomplete)
            ->assertSessionHasNoErrors();
        $this->assertSame('rejected', $incomplete->fresh()->status);
    }

    public function test_documents_rejected_in_an_earlier_review_keep_that_decision(): void
    {
        Notification::fake();
        $earlierReviewer = User::factory()->admin()->create();
        $old = KycDocument::factory()->for($this->applicant)->ofType('national_id')->rejected('Blurred.')->create([
            'reviewed_by' => $earlierReviewer->id,
        ]);

        $this->decide(['decision' => 'approve']);

        $this->assertSame('rejected', $old->fresh()->status);
        $this->assertSame($earlierReviewer->id, $old->fresh()->reviewed_by);
        $this->assertSame([$this->document->id], AuditLog::sole()->details['document_ids']);
    }

    public function test_investors_are_verified_the_same_way(): void
    {
        Notification::fake();
        $investor = InvestorProfile::factory()->create()->user;
        KycDocument::factory()->for($investor)->ofType('national_id')->create();

        $this->decide(['decision' => 'approve'], $investor)->assertSessionHasNoErrors();

        $this->assertSame('verified', $investor->fresh()->status);
        Notification::assertSentTo($investor, VerificationDecided::class);
    }

    public function test_an_admin_account_cannot_be_decided(): void
    {
        $otherAdmin = User::factory()->admin()->create(['status' => 'pending_verification']);

        $this->decide(['decision' => 'approve'], $otherAdmin)->assertNotFound();

        $this->assertSame('pending_verification', $otherAdmin->fresh()->status);
    }

    public function test_if_the_audit_entry_cannot_be_written_the_whole_decision_is_rolled_back(): void
    {
        Notification::fake();
        // Make the last step of the transaction fail.
        AuditLog::creating(fn () => throw new RuntimeException('Audit log unavailable'));

        $this->decide(['decision' => 'approve'])->assertServerError();

        $this->assertNothingChanged();
        Notification::assertNothingSent();
    }

    private function assertNothingChanged(): void
    {
        $this->assertSame('pending_verification', $this->applicant->fresh()->status);
        $this->assertNull($this->applicant->fresh()->status_reason);
        $this->assertSame('pending', $this->document->fresh()->status);
        $this->assertNull($this->document->fresh()->reviewed_by);
        $this->assertSame(0, AuditLog::count());
    }
}
