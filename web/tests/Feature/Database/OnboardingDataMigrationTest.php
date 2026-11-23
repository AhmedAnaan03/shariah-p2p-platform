<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PB-53's data migrations move existing documents into slots and keep queue state; PB-59 drops
 * the legacy address columns. Each rolls back cleanly to exactly the data that was there before.
 *
 * (PB-53's single-line address migration is no longer exercised with old data: PB-59 removed the
 * parser it used, since every environment had already run it. See docs/decisions.md.)
 *
 * Like MigrationRollbackTest, it doesn't use RefreshDatabase (schema changes can't run inside a
 * transaction) and leaves the database fully migrated for the tests after it.
 */
class OnboardingDataMigrationTest extends TestCase
{
    private const DOCUMENT_SLOTS_MIGRATION = '2026_11_19_000003_add_document_slots_to_kyc_documents';

    private const DROP_LEGACY_ADDRESS_MIGRATION = '2026_11_23_000001_drop_legacy_address_columns';

    protected function tearDown(): void
    {
        // Whatever happened, leave a clean, fully migrated database for the next test.
        Artisan::call('migrate:fresh');

        parent::tearDown();
    }

    /**
     * Roll back every migration from $first onwards.
     */
    private function rollBackFrom(string $first): void
    {
        $steps = DB::table('migrations')->where('migration', '>=', $first)->count();
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $steps]));
    }

    private function user(string $email, string $role, string $status = 'pending_verification'): int
    {
        return DB::table('users')->insertGetId([
            'role_id' => DB::table('roles')->where('name', $role)->value('id'),
            'name' => $email, 'email' => $email, 'password' => 'x', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function borrowerProfile(int $userId): void
    {
        DB::table('borrower_profiles')->insert([
            'user_id' => $userId, 'borrower_type' => 'individual', 'phone' => '+960 7771234', 'national_id_no' => 'cipher',
            'address_house' => 'H. Sea Breeze', 'address_road' => 'Nirolhu Magu',
            'island_id' => DB::table('islands')->where('name', 'Hulhumalé')->value('id'), 'postcode' => '23000',
            'declared_annual_income' => 120000, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function document(int $userId, string $type, string $status, string $createdAt): int
    {
        return DB::table('kyc_documents')->insertGetId([
            'user_id' => $userId, 'document_type' => $type, 'original_name' => "{$type}.pdf", 'storage_path' => "{$userId}/{$createdAt}.pdf",
            'mime_type' => 'application/pdf', 'size_bytes' => 1000, 'status' => $status,
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    public function test_documents_move_into_slots_and_queue_state_is_kept_then_both_roll_back(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh'));
        $this->rollBackFrom(self::DOCUMENT_SLOTS_MIGRATION);
        $this->assertFalse(Schema::hasColumn('kyc_documents', 'replaced_at'), 'Rolled back to before document slots.');

        // Before: the old app allowed the same type twice (a rejected ID, then a new one).
        $inQueue = $this->user('in-queue@example.com', 'borrower');
        $noDocuments = $this->user('no-documents@example.com', 'borrower');
        $this->borrowerProfile($inQueue);
        $this->borrowerProfile($noDocuments);
        $oldId = $this->document($inQueue, 'national_id', 'rejected', '2026-11-12 10:00:00');
        $newId = $this->document($inQueue, 'national_id', 'pending', '2026-11-13 10:00:00');

        $this->assertSame(0, Artisan::call('migrate'));

        // The newest upload holds the slot; the older one is kept, marked replaced.
        $this->assertSame('2026-11-13 10:00:00', DB::table('kyc_documents')->where('id', $oldId)->value('replaced_at'));
        $this->assertNull(DB::table('kyc_documents')->where('id', $newId)->value('replaced_at'));
        $this->assertSame('national_id', DB::table('kyc_documents')->where('id', $newId)->value('current_slot'));
        $this->assertNull(DB::table('kyc_documents')->where('id', $oldId)->value('current_slot'));
        $this->assertSame(2, DB::table('kyc_documents')->count(), 'No document row is removed.');

        // The account that was in the old queue counts as submitted; the other doesn't.
        $this->assertNotNull(DB::table('users')->where('id', $inQueue)->value('verification_submitted_at'));
        $this->assertNull(DB::table('users')->where('id', $noDocuments)->value('verification_submitted_at'));

        // Rolling back gives back every document as it was.
        $this->rollBackFrom(self::DOCUMENT_SLOTS_MIGRATION);
        $this->assertFalse(Schema::hasColumn('kyc_documents', 'current_slot'));
        $this->assertFalse(Schema::hasColumn('users', 'verification_submitted_at'));
        $this->assertSame(2, DB::table('kyc_documents')->count());
        $this->assertSame('rejected', DB::table('kyc_documents')->where('id', $oldId)->value('status'));

        $this->assertSame(0, Artisan::call('migrate'));
    }

    public function test_the_legacy_address_columns_are_dropped_and_come_back_on_rollback(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh'));

        foreach (['borrower_profiles', 'investor_profiles'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'address_legacy'));
            $this->assertFalse(Schema::hasColumn($table, 'address_needs_review'));
        }

        $this->rollBackFrom(self::DROP_LEGACY_ADDRESS_MIGRATION);
        foreach (['borrower_profiles', 'investor_profiles'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'address_legacy'));
            $this->assertTrue(Schema::hasColumn($table, 'address_needs_review'));
        }

        $this->assertSame(0, Artisan::call('migrate'));
        $this->assertFalse(Schema::hasColumn('borrower_profiles', 'address_legacy'));
    }

    public function test_the_island_list_migration_rolls_back_and_reloads(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh'));
        $this->rollBackFrom('2026_11_19_000001_create_atolls_and_islands_tables');

        $this->assertFalse(Schema::hasTable('islands'));
        $this->assertFalse(Schema::hasTable('atolls'));

        $this->assertSame(0, Artisan::call('migrate'));
        $this->assertSame(188, DB::table('islands')->count());
    }
}
