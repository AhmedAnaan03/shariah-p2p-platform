<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PB-53: existing data moves into the new structure without loss, and the migrations roll
 * back cleanly to exactly what was there before.
 *
 *   - single-line addresses → house, road, island, postcode, with the original text kept in
 *     address_legacy and the profile flagged for the user to check;
 *   - documents → slots: the newest upload of each type is current, older ones are replaced;
 *   - accounts that were in the admin queue count as submitted.
 *
 * Like MigrationRollbackTest, it doesn't use RefreshDatabase (schema changes can't run inside a
 * transaction) and leaves the database fully migrated for the tests after it.
 */
class OnboardingDataMigrationTest extends TestCase
{
    private const FIRST_PB53_DATA_MIGRATION = '2026_11_19_000002_structure_profile_addresses';

    protected function tearDown(): void
    {
        // Whatever happened, leave a clean, fully migrated database for the next test.
        Artisan::call('migrate:fresh');

        parent::tearDown();
    }

    /**
     * Back to the schema just before PB-53's data migrations, keeping the island list.
     */
    private function rollBackToBeforePb53(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh'));
        $steps = DB::table('migrations')->where('migration', '>=', self::FIRST_PB53_DATA_MIGRATION)->count();
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $steps]));
        $this->assertTrue(Schema::hasColumn('borrower_profiles', 'address'), 'Rolled back to the old single-line address.');
    }

    private function user(string $email, string $role, string $status = 'pending_verification'): int
    {
        return DB::table('users')->insertGetId([
            'role_id' => DB::table('roles')->where('name', $role)->value('id'),
            'name' => $email, 'email' => $email, 'password' => 'x', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function borrowerProfile(int $userId, string $address, string $type = 'individual'): int
    {
        return DB::table('borrower_profiles')->insertGetId([
            'user_id' => $userId, 'borrower_type' => $type, 'phone' => '+960 7771234', 'national_id_no' => 'cipher',
            'address' => $address, 'declared_annual_income' => 100000,
            'business_name' => $type === 'sme' ? 'Reef Catch' : null,
            'created_at' => now(), 'updated_at' => now(),
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

    public function test_addresses_documents_and_queue_state_move_into_the_new_structure_and_back(): void
    {
        $this->rollBackToBeforePb53();

        // Before: a borrower with a full one-line address, an investor whose island name is
        // used in two atolls, and one with text the parser can't read.
        $parsed = $this->user('parsed@example.com', 'borrower');
        $ambiguous = $this->user('ambiguous@example.com', 'investor');
        $unreadable = $this->user('unreadable@example.com', 'borrower');
        $parsedProfile = $this->borrowerProfile($parsed, 'H. Blue Lagoon, Majeedhee Magu, Malé 20026');
        $ambiguousProfile = DB::table('investor_profiles')->insertGetId([
            'user_id' => $ambiguous, 'phone' => '+960 9601234', 'national_id_no' => 'cipher', 'address' => 'Fehivina, Thinadhoo',
            'shariah_only' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $unreadableProfile = $this->borrowerProfile($unreadable, 'qwertyuiop');

        // The old app allowed the same type twice: a rejected ID, then a new one.
        $oldId = $this->document($parsed, 'national_id', 'rejected', '2026-11-12 10:00:00');
        $newId = $this->document($parsed, 'national_id', 'pending', '2026-11-13 10:00:00');
        $this->document($ambiguous, 'national_id', 'pending', '2026-11-13 11:00:00');

        $this->assertSame(0, Artisan::call('migrate'));

        // Addresses: the original text kept, a best guess filled in, every profile flagged.
        $row = DB::table('borrower_profiles')->find($parsedProfile);
        $this->assertSame('H. Blue Lagoon, Majeedhee Magu, Malé 20026', $row->address_legacy);
        $this->assertSame('H. Blue Lagoon', $row->address_house);
        $this->assertSame('Majeedhee Magu', $row->address_road);
        $this->assertSame('Malé', DB::table('islands')->where('id', $row->island_id)->value('name'));
        $this->assertSame('20026', $row->postcode);
        $this->assertSame(1, (int) $row->address_needs_review);

        $row = DB::table('investor_profiles')->find($ambiguousProfile);
        $this->assertSame('Fehivina, Thinadhoo', $row->address_legacy);
        $this->assertNull($row->island_id, 'Thinadhoo is in two atolls, so the user picks.');
        $this->assertSame(1, (int) $row->address_needs_review);

        $row = DB::table('borrower_profiles')->find($unreadableProfile);
        $this->assertSame('qwertyuiop', $row->address_legacy);
        $this->assertNull($row->island_id);
        $this->assertNull($row->postcode);
        $this->assertFalse(Schema::hasColumn('borrower_profiles', 'address'));

        // Documents: the newest upload holds the slot; the older one is kept, marked replaced.
        $this->assertSame('2026-11-13 10:00:00', DB::table('kyc_documents')->where('id', $oldId)->value('replaced_at'));
        $this->assertNull(DB::table('kyc_documents')->where('id', $newId)->value('replaced_at'));
        $this->assertSame('national_id', DB::table('kyc_documents')->where('id', $newId)->value('current_slot'));
        $this->assertNull(DB::table('kyc_documents')->where('id', $oldId)->value('current_slot'));
        $this->assertSame(3, DB::table('kyc_documents')->count(), 'No document row is removed.');

        // Queue state: the borrower with a profile and a pending ID was in the old queue.
        $this->assertNotNull(DB::table('users')->where('id', $parsed)->value('verification_submitted_at'));
        $this->assertNotNull(DB::table('users')->where('id', $ambiguous)->value('verification_submitted_at'));
        $this->assertNull(DB::table('users')->where('id', $unreadable)->value('verification_submitted_at'), 'No ID document, so it was not in the queue.');

        // Rolling back gives back exactly the original text and every document as it was.
        $steps = DB::table('migrations')->where('migration', '>=', self::FIRST_PB53_DATA_MIGRATION)->count();
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $steps]));

        $this->assertSame('H. Blue Lagoon, Majeedhee Magu, Malé 20026', DB::table('borrower_profiles')->where('id', $parsedProfile)->value('address'));
        $this->assertSame('Fehivina, Thinadhoo', DB::table('investor_profiles')->where('id', $ambiguousProfile)->value('address'));
        $this->assertSame('qwertyuiop', DB::table('borrower_profiles')->where('id', $unreadableProfile)->value('address'));
        $this->assertFalse(Schema::hasColumn('kyc_documents', 'replaced_at'));
        $this->assertFalse(Schema::hasColumn('kyc_documents', 'current_slot'));
        $this->assertFalse(Schema::hasColumn('users', 'verification_submitted_at'));
        $this->assertSame(3, DB::table('kyc_documents')->count());
        $this->assertSame('rejected', DB::table('kyc_documents')->where('id', $oldId)->value('status'));

        // And forward again from there.
        $this->assertSame(0, Artisan::call('migrate'));
    }

    public function test_rolling_back_an_address_the_user_has_checked_keeps_what_they_entered(): void
    {
        $this->rollBackToBeforePb53();
        $user = $this->user('checked@example.com', 'borrower');
        $profile = $this->borrowerProfile($user, 'Old text, Malé');
        $this->assertSame(0, Artisan::call('migrate'));

        // The user completes the address in the wizard.
        $hulhumale = DB::table('islands')->where('name', 'Hulhumalé')->value('id');
        DB::table('borrower_profiles')->where('id', $profile)->update([
            'address_house' => 'H. Sea Breeze', 'address_road' => 'Nirolhu Magu', 'island_id' => $hulhumale,
            'postcode' => '23000', 'address_needs_review' => false,
        ]);

        $steps = DB::table('migrations')->where('migration', '>=', self::FIRST_PB53_DATA_MIGRATION)->count();
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $steps]));

        $this->assertSame('H. Sea Breeze, Nirolhu Magu, Hulhumalé, Malé City, 23000', DB::table('borrower_profiles')->where('id', $profile)->value('address'));
    }

    public function test_the_island_list_migration_rolls_back_and_reloads(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh'));
        $steps = DB::table('migrations')->where('migration', '>=', '2026_11_19_000001_create_atolls_and_islands_tables')->count();

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $steps]));
        $this->assertFalse(Schema::hasTable('islands'));
        $this->assertFalse(Schema::hasTable('atolls'));

        $this->assertSame(0, Artisan::call('migrate'));
        $this->assertSame(188, DB::table('islands')->count());
    }
}
