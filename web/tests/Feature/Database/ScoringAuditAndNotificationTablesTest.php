<?php

namespace Tests\Feature\Database;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\Concerns\AssertsDatabaseConstraints;
use Tests\TestCase;

/**
 * risk_scores, kyc_documents, audit_logs and notifications.
 */
class ScoringAuditAndNotificationTablesTest extends TestCase
{
    use AssertsDatabaseConstraints, RefreshDatabase;

    public function test_a_pending_score_stores_its_input_and_starts_with_zero_attempts(): void
    {
        $score = Campaign::factory()->create()->riskScores()->create([
            'input_features' => ['amount_requested' => 50000, 'tenure_months' => 12],
        ]);

        $fresh = $score->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame(0, $fresh->attempts);
        // assertEquals, not assertSame: MySQL's JSON type re-orders object keys when it stores
        // them, and key order carries no meaning in a JSON object.
        $this->assertEquals(['amount_requested' => 50000, 'tenure_months' => 12], $fresh->input_features);
        $this->assertNull($fresh->grade);
    }

    public function test_a_probability_above_one_is_rejected(): void
    {
        $campaign = Campaign::factory()->create();

        $this->assertCheckViolated('risk_scores_probability_check', fn () => $campaign->riskScores()->create([
            'input_features' => [], 'probability_of_default' => 1.2, 'grade' => 'E',
        ]));
    }

    public function test_a_grade_outside_a_to_e_is_rejected(): void
    {
        $campaign = Campaign::factory()->create();

        $this->assertQueryRejected("Data truncated for column 'grade'", fn () => $campaign->riskScores()->create([
            'input_features' => [], 'probability_of_default' => 0.5, 'grade' => 'F',
        ]));
    }

    public function test_a_kyc_document_starts_pending_and_records_its_reviewer(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $document = $owner->kycDocuments()->create([
            'document_type' => 'national_id', 'original_name' => 'id-card.pdf',
            'storage_path' => 'kyc/1/abc.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 120_000,
        ]);
        $this->assertSame('pending', $document->fresh()->status);

        $document->forceFill(['status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();

        $this->assertTrue($document->fresh()->reviewer->is($admin));
    }

    public function test_an_audit_entry_records_actor_subject_and_details_with_no_updated_at(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = Campaign::factory()->create();

        $entry = AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'campaign.suspended',
            'subject_type' => $campaign->getMorphClass(),
            'subject_id' => $campaign->id,
            'details' => ['reason' => 'Documents expired'],
        ]);

        $fresh = $entry->fresh();
        $this->assertTrue($fresh->actor->is($admin));
        $this->assertTrue($fresh->subject->is($campaign));
        $this->assertSame(['reason' => 'Documents expired'], $fresh->details);
        $this->assertNotNull($fresh->created_at);
        $this->assertArrayNotHasKey('updated_at', $fresh->getAttributes());
    }

    public function test_an_audit_entry_from_a_scheduled_job_has_no_actor(): void
    {
        $entry = AuditLog::create(['action' => 'campaign.deadline_missed']);

        $this->assertNull($entry->fresh()->actor);
    }

    public function test_laravel_database_notifications_use_the_notifications_table(): void
    {
        $user = User::factory()->create();

        $user->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['message' => 'Your account is under review.'];
            }
        });

        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->assertSame('Your account is under review.', $user->notifications()->first()->data['message']);
    }
}
