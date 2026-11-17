<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\KycDocument;
use App\Models\User;
use App\Notifications\CampaignDecided;
use App\Services\KycDocumentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\MakesKycFiles;
use Tests\TestCase;

/**
 * PB-15: an administrator approves, rejects or suspends campaigns. Approved campaigns appear
 * in the marketplace with a funding deadline; reject and suspend need a reason and notify the
 * borrower; every decision is recorded in the audit log.
 */
class CampaignReviewTest extends TestCase
{
    use MakesKycFiles, RefreshDatabase;

    private User $admin;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        Storage::fake(KycDocumentStore::DISK);
        Notification::fake();

        $this->admin = User::factory()->admin()->create();
        $this->campaign = Campaign::factory()->submitted()->create(['title' => 'Second delivery van']);
    }

    private function decide(array $input, ?Campaign $campaign = null, ?User $as = null)
    {
        $campaign ??= $this->campaign;

        return $this->actingAs($as ?? $this->admin)
            ->from("/admin/campaigns/{$campaign->id}")
            ->post("/admin/campaigns/{$campaign->id}/decision", $input);
    }

    private function attachReport(): KycDocument
    {
        return app(KycDocumentStore::class)->store($this->campaign->borrower, $this->pdfFile('cis.pdf'), KycDocument::TYPE_CREDIT_REPORT, $this->campaign);
    }

    // ---- Who may review --------------------------------------------------------------------

    public function test_only_administrators_can_open_or_decide(): void
    {
        $others = [
            User::factory()->borrower()->verified()->create(),
            $this->campaign->borrower,
            User::factory()->investor()->verified()->create(),
        ];

        foreach ($others as $user) {
            $this->actingAs($user)->get('/admin/campaigns')->assertForbidden();
            $this->actingAs($user)->get("/admin/campaigns/{$this->campaign->id}")->assertForbidden();
            $this->decide(['decision' => 'approve'], as: $user)->assertForbidden();
        }

        $this->assertSame('submitted', $this->campaign->fresh()->status);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/admin/campaigns')->assertRedirect('/login');
        $this->post("/admin/campaigns/{$this->campaign->id}/decision", ['decision' => 'approve'])->assertRedirect('/login');
    }

    // ---- The queue and the review page ---------------------------------------------------------

    public function test_the_queue_lists_submitted_requests_oldest_first_and_live_campaigns_separately(): void
    {
        $older = Campaign::factory()->submitted()->create(['submitted_at' => now()->subDays(3)]);
        $live = Campaign::factory()->live()->create();
        Campaign::factory()->create(); // a draft
        Campaign::factory()->submitted()->create()->forceFill(['status' => 'rejected'])->save();

        $this->actingAs($this->admin)->get('/admin/campaigns')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Campaigns/Index')
                ->has('toReview', 2)
                ->where('toReview.0.id', $older->id)
                ->where('toReview.1.id', $this->campaign->id)
                ->has('live', 1)
                ->where('live.0.id', $live->id)
            );
    }

    public function test_the_review_page_shows_the_self_declared_answers_next_to_the_credit_report(): void
    {
        $report = $this->attachReport();

        $this->actingAs($this->admin)->get("/admin/campaigns/{$this->campaign->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Campaigns/Show')
                ->where('creditHistory', [
                    ['label' => 'Open loans, financing facilities and credit cards', 'value' => '3', 'mono' => true],
                    ['label' => 'Repayments 30 or more days late in the past 2 years', 'value' => '1', 'mono' => true],
                    ['label' => 'Court judgments, bankruptcies or debts written off', 'value' => '0', 'mono' => true],
                ])
                ->where('creditReport.id', $report->id)
                ->where('creditReport.type_label', 'MMA credit report')
                ->where('creditReport.url', route('documents.show', $report))
                ->missing('creditReport.storage_path')
                ->has('finances', 4)
                ->has('ratios', 3)
                ->where('decisions', ['approve', 'reject'])
                ->where('fundingDays', 30)
                ->where('deadlineIfApproved', '18 Dec 2026')
            );
    }

    public function test_the_review_page_says_when_no_report_was_attached(): void
    {
        $this->actingAs($this->admin)->get("/admin/campaigns/{$this->campaign->id}")
            ->assertInertia(fn (Assert $page) => $page->where('creditReport', null)->has('creditHistory', 3));
    }

    /**
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function tenures(): array
    {
        return ['12 months' => [12, true], '36 months' => [36, false], '48 months' => [48, true], '60 months' => [60, false]];
    }

    #[DataProvider('tenures')]
    public function test_the_review_page_flags_tenures_outside_the_models_training_range(int $tenure, bool $flagged): void
    {
        $this->campaign->update(['tenure_months' => $tenure]);

        $this->actingAs($this->admin)->get("/admin/campaigns/{$this->campaign->id}")
            ->assertInertia(fn (Assert $page) => $page->where('tenureOutsideTrainingRange', $flagged));
    }

    // ---- Approve -----------------------------------------------------------------------------

    public function test_approving_makes_the_campaign_live_with_a_30_day_funding_deadline(): void
    {
        $this->decide(['decision' => 'approve'])
            ->assertRedirect('/admin/campaigns')
            ->assertSessionHas('status', '"Second delivery van" is live until 18 Dec 2026. The borrower has been notified.');

        $campaign = $this->campaign->fresh();
        $this->assertSame('live', $campaign->status);
        $this->assertNull($campaign->status_reason);
        $this->assertTrue($campaign->approved_at->equalTo(now()));
        // The end of 18 Dec in the Maldives (UTC+5), stored in UTC.
        $this->assertSame('2026-12-18 18:59:59', $campaign->funding_deadline->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-18 23:59:59', $campaign->funding_deadline->setTimezone('Indian/Maldives')->format('Y-m-d H:i:s'));
    }

    public function test_an_approved_campaign_appears_in_the_marketplace_with_its_deadline(): void
    {
        $this->decide(['decision' => 'approve']);

        $investor = User::factory()->investor()->verified()->create();
        $this->actingAs($investor)->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Investor/Campaigns/Index')
                ->has('campaigns', 1)
                ->where('campaigns.0.id', $this->campaign->id)
                ->where('campaigns.0.funding_deadline', '2026-12-18T18:59:59+00:00')
                ->where('campaigns.0.funding_deadline_label', '18 Dec 2026')
            );
    }

    public function test_approving_writes_an_audit_entry_and_notifies_the_borrower(): void
    {
        $this->decide(['decision' => 'approve']);

        $entry = AuditLog::where('action', 'campaign.approved')->sole();
        $this->assertSame($this->admin->id, $entry->actor_id);
        $this->assertSame(Campaign::class, $entry->subject_type);
        $this->assertSame($this->campaign->id, $entry->subject_id);
        $this->assertSame('submitted', $entry->details['status_before']);
        $this->assertSame('live', $entry->details['status_after']);
        $this->assertSame('2026-12-18T18:59:59+00:00', $entry->details['funding_deadline']);

        Notification::assertSentTo($this->campaign->borrower, CampaignDecided::class,
            fn (CampaignDecided $n) => $n->decision === 'approve' && $n->reason === null);
    }

    public function test_approving_marks_an_attached_credit_report_as_checked(): void
    {
        $report = $this->attachReport();

        $this->decide(['decision' => 'approve']);

        $report = $report->fresh();
        $this->assertSame('approved', $report->status);
        $this->assertSame($this->admin->id, $report->reviewed_by);
        $this->assertTrue($report->reviewed_at->equalTo(now()));
    }

    // ---- Reject ----------------------------------------------------------------------------

    public function test_rejecting_needs_a_reason_and_notifies_the_borrower_with_it(): void
    {
        $report = $this->attachReport();
        $reason = 'The late payments in the credit report do not match your answers.';

        $this->decide(['decision' => 'reject', 'reason' => $reason])
            ->assertRedirect('/admin/campaigns')
            ->assertSessionHas('status', '"Second delivery van" has been rejected and the borrower notified of the reason.');

        $campaign = $this->campaign->fresh();
        $this->assertSame('rejected', $campaign->status);
        $this->assertSame($reason, $campaign->status_reason);
        $this->assertNull($campaign->funding_deadline);
        $this->assertSame('rejected', $report->fresh()->status);
        $this->assertSame($reason, $report->fresh()->rejection_reason);

        $entry = AuditLog::where('action', 'campaign.rejected')->sole();
        $this->assertSame($reason, $entry->details['reason']);

        Notification::assertSentTo($campaign->borrower, CampaignDecided::class,
            fn (CampaignDecided $n) => $n->decision === 'reject' && $n->reason === $reason);
    }

    // ---- Suspend -----------------------------------------------------------------------------

    public function test_suspending_a_live_campaign_takes_it_off_the_marketplace(): void
    {
        $live = Campaign::factory()->live()->create();
        $reason = 'The business registration has expired.';

        $this->decide(['decision' => 'suspend', 'reason' => $reason], $live)->assertRedirect('/admin/campaigns');

        $live = $live->fresh();
        $this->assertSame('suspended', $live->status);
        $this->assertSame($reason, $live->status_reason);
        $this->assertSame(0, Campaign::listed()->count());

        $entry = AuditLog::where('action', 'campaign.suspended')->sole();
        $this->assertSame('live', $entry->details['status_before']);
        $this->assertSame($reason, $entry->details['reason']);

        Notification::assertSentTo($live->borrower, CampaignDecided::class,
            fn (CampaignDecided $n) => $n->decision === 'suspend' && $n->reason === $reason);
    }

    public function test_a_live_campaign_offers_only_suspend(): void
    {
        $live = Campaign::factory()->live()->create();

        $this->actingAs($this->admin)->get("/admin/campaigns/{$live->id}")
            ->assertInertia(fn (Assert $page) => $page->where('decisions', ['suspend']));
    }

    // ---- Validation (blank, invalid, valid) ----------------------------------------------------

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     */
    public static function invalidDecisions(): array
    {
        return [
            'no decision' => [[], 'decision', 'Choose a decision.'],
            'unknown decision' => [['decision' => 'delete'], 'decision', 'Choose a decision from the list.'],
            'reject without a reason' => [['decision' => 'reject'], 'reason', 'Give a reason. The borrower will see it.'],
            'reject with a blank reason' => [['decision' => 'reject', 'reason' => '   '], 'reason', 'Give a reason. The borrower will see it.'],
            'reject with a short reason' => [['decision' => 'reject', 'reason' => 'No.'], 'reason', 'Write at least 10 characters so the borrower knows why.'],
            'reject with a long reason' => [['decision' => 'reject', 'reason' => str_repeat('a', 256)], 'reason', 'The reason must be 255 characters or fewer.'],
            'suspend without a reason' => [['decision' => 'suspend'], 'reason', 'Give a reason. The borrower will see it.'],
        ];
    }

    #[DataProvider('invalidDecisions')]
    public function test_an_invalid_decision_changes_nothing(array $input, string $field, string $message): void
    {
        $this->decide($input)->assertSessionHasErrors([$field => $message]);

        $this->assertSame('submitted', $this->campaign->fresh()->status);
        $this->assertSame(0, AuditLog::count());
        Notification::assertNothingSent();
    }

    /**
     * Decisions that don't fit the campaign's status.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function wrongStatus(): array
    {
        $decided = 'This request has already been decided, so it is no longer waiting for review. It is ';

        return [
            'approve a live campaign' => ['live', 'approve', $decided.'live.'],
            'reject a live campaign' => ['live', 'reject', $decided.'live.'],
            'approve a rejected request' => ['rejected', 'approve', $decided.'rejected.'],
            'approve a suspended campaign' => ['suspended', 'approve', $decided.'suspended.'],
            'approve a draft' => ['draft', 'approve', $decided.'draft.'],
            'suspend a submitted request' => ['submitted', 'suspend', 'Only a live campaign can be suspended. This one is submitted.'],
            'suspend a rejected request' => ['rejected', 'suspend', 'Only a live campaign can be suspended. This one is rejected.'],
            'suspend twice' => ['suspended', 'suspend', 'Only a live campaign can be suspended. This one is suspended.'],
        ];
    }

    #[DataProvider('wrongStatus')]
    public function test_a_decision_that_does_not_fit_the_status_is_refused(string $status, string $decision, string $message): void
    {
        $this->campaign->forceFill(['status' => $status])->save();

        $this->decide(['decision' => $decision, 'reason' => 'A reason long enough to pass.'])
            ->assertSessionHasErrors(['decision' => $message]);

        $this->assertSame($status, $this->campaign->fresh()->status);
        $this->assertSame(0, AuditLog::count());
        Notification::assertNothingSent();
    }

    public function test_a_failure_part_way_changes_nothing_and_sends_nothing(): void
    {
        $report = $this->attachReport();
        Event::listen('eloquent.creating: '.AuditLog::class, fn () => throw new RuntimeException('Audit log unavailable'));

        $this->withoutExceptionHandling();
        try {
            $this->decide(['decision' => 'reject', 'reason' => 'Not suitable for the marketplace.']);
            $this->fail('The decision should have failed.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame('submitted', $this->campaign->fresh()->status);
        $this->assertSame('pending', $report->fresh()->status);
        Notification::assertNothingSent();
    }

    // ---- The notification --------------------------------------------------------------------

    public function test_the_email_states_the_outcome_and_reason_and_links_to_the_request(): void
    {
        $borrower = $this->campaign->borrower;
        $reason = 'The business registration has expired.';

        $mail = (new CampaignDecided($this->campaign, 'reject', $reason))->toMail($borrower);
        $this->assertSame('Your financing request was not approved', $mail->subject);
        $this->assertContains("Reason: {$reason}", $mail->introLines);
        $this->assertSame(route('borrower.campaigns.show', $this->campaign), $mail->actionUrl);

        $data = (new CampaignDecided($this->campaign, 'reject', $reason))->toArray($borrower);
        $this->assertSame(['campaign_id' => $this->campaign->id, 'decision' => 'reject', 'reason' => $reason, 'message' => 'Your financing request was not approved'], $data);
    }

    public function test_the_admin_portal_counts_the_requests_waiting_for_review(): void
    {
        Campaign::factory()->submitted()->create();
        Campaign::factory()->live()->create();

        $this->actingAs($this->admin)->get('/admin')
            ->assertInertia(fn (Assert $page) => $page->where('campaignsToReview', 2));
    }
}
