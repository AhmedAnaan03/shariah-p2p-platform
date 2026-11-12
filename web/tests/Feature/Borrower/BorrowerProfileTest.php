<?php

namespace Tests\Feature\Borrower;

use App\Models\BorrowerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-09: the borrower / SME profile captures personal and business details, sector, years
 * operating and declared income or revenue.
 */
class BorrowerProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A valid SME profile. Each test changes only what it is about.
     *
     * @return array<string, mixed>
     */
    private function smeInput(array $overrides = []): array
    {
        return array_merge([
            'borrower_type' => 'sme',
            'phone' => '777 1234',
            'national_id_no' => 'a123456',
            'address' => 'H. Blue Lagoon, Majeedhee Magu, Malé',
            'business_name' => 'Reef Catch Pvt Ltd',
            'business_registration_no' => 'C-0123/2019',
            'sector' => 'fisheries',
            'years_operating' => '6',
            'declared_annual_income' => '850000.50',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function individualInput(array $overrides = []): array
    {
        return array_merge([
            'borrower_type' => 'individual',
            'phone' => '+9609876543',
            'national_id_no' => 'A654321',
            'address' => 'M. Sunny Side, Hulhumalé',
            'declared_annual_income' => '180000',
        ], $overrides);
    }

    public function test_a_new_borrower_sees_an_empty_profile_form_with_the_sector_list(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->actingAs($borrower)->get('/borrower/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Borrower/Profile')
                ->where('profile', null)
                ->where('locked', false)
                ->has('sectors', count(BorrowerProfile::SECTORS))
                ->has('borrowerTypes', 2)
            );
    }

    public function test_an_sme_profile_saves_personal_and_business_details(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->actingAs($borrower)->put('/borrower/profile', $this->smeInput())
            ->assertRedirect('/borrower/profile')
            ->assertSessionHas('status', 'Your profile has been saved.');

        $profile = $borrower->fresh()->borrowerProfile;
        $this->assertSame('sme', $profile->borrower_type);
        $this->assertSame('+960 7771234', $profile->phone, 'Phone numbers are stored in one format.');
        $this->assertSame('A123456', $profile->national_id_no, 'The ID number is stored in upper case.');
        $this->assertSame('Reef Catch Pvt Ltd', $profile->business_name);
        $this->assertSame('C-0123/2019', $profile->business_registration_no);
        $this->assertSame('fisheries', $profile->sector);
        $this->assertSame(6, $profile->years_operating);
        $this->assertSame('850000.50', $profile->declared_annual_income);
    }

    public function test_the_national_id_number_is_stored_encrypted(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->actingAs($borrower)->put('/borrower/profile', $this->smeInput());

        $raw = DB::table('borrower_profiles')->where('user_id', $borrower->id)->value('national_id_no');
        $this->assertNotSame('A123456', $raw);
        $this->assertStringNotContainsString('A123456', $raw);
    }

    public function test_an_individual_profile_saves_without_business_details(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->actingAs($borrower)->put('/borrower/profile', $this->individualInput())
            ->assertSessionHasNoErrors();

        $profile = $borrower->fresh()->borrowerProfile;
        $this->assertSame('individual', $profile->borrower_type);
        $this->assertSame('+960 9876543', $profile->phone);
        foreach (BorrowerProfile::BUSINESS_FIELDS as $field) {
            $this->assertNull($profile->{$field}, "{$field} should be NULL for an individual.");
        }
    }

    public function test_business_fields_sent_for_an_individual_are_not_saved(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->actingAs($borrower)->put('/borrower/profile', $this->individualInput([
            'business_name' => 'Should be ignored',
            'sector' => 'retail',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($borrower->fresh()->borrowerProfile->business_name);
        $this->assertNull($borrower->fresh()->borrowerProfile->sector);
    }

    public function test_switching_from_sme_to_individual_clears_the_business_details(): void
    {
        $borrower = User::factory()->borrower()->create();
        $this->actingAs($borrower)->put('/borrower/profile', $this->smeInput());

        $this->actingAs($borrower)->put('/borrower/profile', $this->individualInput());

        $this->assertSame(1, BorrowerProfile::where('user_id', $borrower->id)->count(), 'Saving again updates, not duplicates.');
        $this->assertNull($borrower->fresh()->borrowerProfile->business_name);
    }

    public function test_the_saved_profile_is_shown_back_to_its_owner(): void
    {
        $borrower = User::factory()->borrower()->create();
        $this->actingAs($borrower)->put('/borrower/profile', $this->smeInput());

        $this->actingAs($borrower)->get('/borrower/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.national_id_no', 'A123456')
                ->where('profile.sector', 'fisheries')
            );
    }

    /**
     * Blank and invalid inputs: [changed input, field with the error, expected message].
     *
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidSmeInputs(): array
    {
        $type = 'Choose whether you are borrowing as an individual or as a business (SME).';
        $years = 'Enter how many whole years the business has been operating, from 0 to 100.';
        $income = 'Enter your declared annual income or revenue in MVR, like 250000 or 250000.50.';

        return [
            'blank borrower type' => [['borrower_type' => ''], 'borrower_type', $type],
            'unknown borrower type' => [['borrower_type' => 'company'], 'borrower_type', $type],
            'blank phone' => [['phone' => ''], 'phone', 'Enter your phone number.'],
            'phone with 6 digits' => [['phone' => '777123'], 'phone', 'Enter a 7-digit Maldivian phone number, like 7771234 or +960 7771234.'],
            'phone with another country code' => [['phone' => '+447771234567'], 'phone', 'Enter a 7-digit Maldivian phone number, like 7771234 or +960 7771234.'],
            'phone with letters' => [['phone' => '777abcd'], 'phone', 'Enter a 7-digit Maldivian phone number, like 7771234 or +960 7771234.'],
            'blank national ID' => [['national_id_no' => ''], 'national_id_no', 'Enter your national ID card number.'],
            'national ID without the A' => [['national_id_no' => '123456'], 'national_id_no', 'Enter your ID card number as the letter A and 6 digits, like A123456.'],
            'national ID with 7 digits' => [['national_id_no' => 'A1234567'], 'national_id_no', 'Enter your ID card number as the letter A and 6 digits, like A123456.'],
            'blank address' => [['address' => ''], 'address', 'Enter your address.'],
            'address over 500 characters' => [['address' => str_repeat('a', 501)], 'address', 'Your address must be 500 characters or fewer.'],
            'blank business name' => [['business_name' => ''], 'business_name', 'Enter your business name.'],
            'business name over 255 characters' => [['business_name' => str_repeat('a', 256)], 'business_name', 'The business name must be 255 characters or fewer.'],
            'blank registration number' => [['business_registration_no' => ''], 'business_registration_no', 'Enter your business registration number.'],
            'registration number with symbols' => [['business_registration_no' => 'C-01; DROP'], 'business_registration_no', 'Use only letters, numbers, / and - in the registration number.'],
            'blank sector' => [['sector' => ''], 'sector', 'Choose your business sector.'],
            'sector not on the list' => [['sector' => 'gambling'], 'sector', 'Choose your business sector from the list.'],
            'blank years operating' => [['years_operating' => ''], 'years_operating', $years],
            'years operating not a whole number' => [['years_operating' => '2.5'], 'years_operating', $years],
            'negative years operating' => [['years_operating' => '-1'], 'years_operating', $years],
            'years operating over 100' => [['years_operating' => '101'], 'years_operating', $years],
            'blank income' => [['declared_annual_income' => ''], 'declared_annual_income', $income],
            'income in words' => [['declared_annual_income' => 'a lot'], 'declared_annual_income', $income],
            'income with 3 decimal places' => [['declared_annual_income' => '1000.555'], 'declared_annual_income', 'Enter the amount with at most 2 decimal places.'],
            'negative income' => [['declared_annual_income' => '-5'], 'declared_annual_income', 'The amount can\'t be negative.'],
            'income too large for the column' => [['declared_annual_income' => '10000000000000'], 'declared_annual_income', 'The amount is too large.'],
        ];
    }

    #[DataProvider('invalidSmeInputs')]
    public function test_invalid_input_is_rejected_with_a_clear_message(array $change, string $field, string $message): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->actingAs($borrower)
            ->from('/borrower/profile')
            ->put('/borrower/profile', $this->smeInput($change))
            ->assertRedirect('/borrower/profile')
            ->assertSessionHasErrors([$field => $message]);

        $this->assertNull($borrower->fresh()->borrowerProfile, 'Nothing is saved when validation fails.');
    }

    /**
     * Boundary values that must be accepted.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function boundaryValidInputs(): array
    {
        return [
            'zero years operating (a new business)' => [['years_operating' => '0']],
            '100 years operating' => [['years_operating' => '100']],
            'zero income' => [['declared_annual_income' => '0']],
            'largest income the column holds' => [['declared_annual_income' => '9999999999999.99']],
            'landline starting with 3' => [['phone' => '3301234']],
            'phone written with dashes' => [['phone' => '+960-777-1234']],
        ];
    }

    #[DataProvider('boundaryValidInputs')]
    public function test_boundary_values_are_accepted(array $change): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->actingAs($borrower)->put('/borrower/profile', $this->smeInput($change))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($borrower->fresh()->borrowerProfile);
    }

    public function test_an_individual_does_not_need_business_details(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->actingAs($borrower)->put('/borrower/profile', $this->individualInput())
            ->assertSessionDoesntHaveErrors(['business_name', 'business_registration_no', 'sector', 'years_operating']);
    }

    public function test_a_borrower_cannot_save_a_profile_for_someone_else(): void
    {
        $borrower = User::factory()->borrower()->create();
        $other = User::factory()->borrower()->create();

        $this->actingAs($borrower)->put('/borrower/profile', $this->smeInput(['user_id' => $other->id]));

        $this->assertNotNull($borrower->fresh()->borrowerProfile);
        $this->assertNull($other->fresh()->borrowerProfile);
    }

    public function test_a_verified_borrowers_profile_is_locked(): void
    {
        $borrower = User::factory()->borrower()->verified()->create();
        BorrowerProfile::factory()->for($borrower)->create(['business_name' => 'Original Ltd']);

        $this->actingAs($borrower)->put('/borrower/profile', $this->smeInput(['business_name' => 'Changed Ltd']))
            ->assertForbidden();

        $this->assertSame('Original Ltd', $borrower->fresh()->borrowerProfile->business_name);
        $this->actingAs($borrower)->get('/borrower/profile')
            ->assertInertia(fn (Assert $page) => $page->where('locked', true));
    }

    public function test_a_rejected_borrower_who_fixes_their_profile_goes_back_to_the_review_queue(): void
    {
        $borrower = User::factory()->borrower()->create();
        $borrower->forceFill(['status' => 'rejected', 'status_reason' => 'Address does not match.'])->save();

        $this->actingAs($borrower)->put('/borrower/profile', $this->smeInput())->assertSessionHasNoErrors();

        $this->assertSame('pending_verification', $borrower->fresh()->status);
        $this->assertNull($borrower->fresh()->status_reason);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function otherRolesOnBorrowerRoutes(): array
    {
        return [
            'investor opens the profile' => ['investor', 'get', '/borrower/profile'],
            'investor saves a profile' => ['investor', 'put', '/borrower/profile'],
            'admin opens the profile' => ['admin', 'get', '/borrower/profile'],
            'admin saves a profile' => ['admin', 'put', '/borrower/profile'],
        ];
    }

    #[DataProvider('otherRolesOnBorrowerRoutes')]
    public function test_only_borrowers_can_use_the_borrower_profile(string $role, string $method, string $url): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)->{$method}($url, $this->smeInput())->assertForbidden();
        $this->assertSame(0, BorrowerProfile::count());
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/borrower/profile')->assertRedirect('/login');
        $this->put('/borrower/profile', $this->smeInput())->assertRedirect('/login');
    }
}
