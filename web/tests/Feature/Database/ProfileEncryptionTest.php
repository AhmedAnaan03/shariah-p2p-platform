<?php

namespace Tests\Feature\Database;

use App\Models\BorrowerProfile;
use App\Models\InvestorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsDatabaseConstraints;
use Tests\TestCase;

/**
 * national_id_no is stored encrypted (Laravel's "encrypted" cast) on both profile tables.
 */
class ProfileEncryptionTest extends TestCase
{
    use AssertsDatabaseConstraints, RefreshDatabase;

    public function test_borrower_national_id_is_stored_as_ciphertext_and_read_back_as_plain_text(): void
    {
        $profile = BorrowerProfile::factory()->create(['national_id_no' => 'A123456']);

        $stored = DB::table('borrower_profiles')->where('id', $profile->id)->value('national_id_no');

        $this->assertStringNotContainsString('A123456', $stored);
        $this->assertSame('A123456', Crypt::decryptString($stored));
        $this->assertSame('A123456', $profile->fresh()->national_id_no);
    }

    public function test_investor_national_id_is_stored_as_ciphertext_and_read_back_as_plain_text(): void
    {
        $profile = InvestorProfile::factory()->create(['national_id_no' => 'A654321']);

        $stored = DB::table('investor_profiles')->where('id', $profile->id)->value('national_id_no');

        $this->assertStringNotContainsString('A654321', $stored);
        $this->assertSame('A654321', Crypt::decryptString($stored));
        $this->assertSame('A654321', $profile->fresh()->national_id_no);
    }

    public function test_the_same_id_encrypts_differently_each_time(): void
    {
        // Each encryption uses a random IV, so equal IDs can't be spotted by comparing rows.
        $first = BorrowerProfile::factory()->create(['national_id_no' => 'A111111']);
        $second = BorrowerProfile::factory()->create(['national_id_no' => 'A111111']);

        $this->assertNotSame(
            DB::table('borrower_profiles')->where('id', $first->id)->value('national_id_no'),
            DB::table('borrower_profiles')->where('id', $second->id)->value('national_id_no'),
        );
    }

    public function test_a_blank_national_id_is_rejected(): void
    {
        $this->assertQueryRejected("Column 'national_id_no' cannot be null", fn () => BorrowerProfile::factory()->create([
            'national_id_no' => null,
        ]));
    }

    public function test_national_id_is_left_out_when_a_profile_is_serialised(): void
    {
        $this->assertArrayNotHasKey('national_id_no', BorrowerProfile::factory()->create()->toArray());
        $this->assertArrayNotHasKey('national_id_no', InvestorProfile::factory()->create()->toArray());
    }

    public function test_an_individual_borrower_needs_no_business_details(): void
    {
        $profile = BorrowerProfile::factory()->individual()->create();

        $this->assertSame('individual', $profile->fresh()->borrower_type);
        $this->assertNull($profile->fresh()->business_name);
    }

    public function test_a_user_cannot_have_two_borrower_profiles(): void
    {
        $profile = BorrowerProfile::factory()->create();

        $this->assertQueryRejected('Duplicate entry', fn () => BorrowerProfile::factory()->create([
            'user_id' => $profile->user_id,
        ]));
    }
}
