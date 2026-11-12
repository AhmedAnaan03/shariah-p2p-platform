<?php

namespace Tests\Feature\Investor;

use App\Models\InvestorProfile;
use App\Models\KycDocument;
use App\Models\User;
use App\Services\KycDocumentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesKycFiles;
use Tests\TestCase;

/**
 * PB-10: the investor completes their profile (and uploads an ID document for PB-11).
 */
class InvestorProfileTest extends TestCase
{
    use MakesKycFiles, RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'phone' => '9601234',
            'national_id_no' => 'A246810',
            'address' => 'G. Coral View, Hulhumalé',
        ], $overrides);
    }

    public function test_a_new_investor_sees_an_empty_profile_form(): void
    {
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->get('/investor/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Investor/Profile')
                ->where('profile', null)
                ->where('locked', false)
            );
    }

    public function test_a_valid_profile_is_saved(): void
    {
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->put('/investor/profile', $this->validInput())
            ->assertRedirect('/investor/profile')
            ->assertSessionHas('status', 'Your profile has been saved.');

        $profile = $investor->fresh()->investorProfile;
        $this->assertSame('+960 9601234', $profile->phone);
        $this->assertSame('A246810', $profile->national_id_no);
        $this->assertSame('G. Coral View, Hulhumalé', $profile->address);
        $this->assertNull($profile->risk_acknowledged_at, 'Saving the profile does not accept the acknowledgement.');
        $this->assertNotSame('A246810', DB::table('investor_profiles')->value('national_id_no'), 'Stored encrypted.');
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'blank phone' => [['phone' => ''], 'phone', 'Enter your phone number.'],
            'phone with 8 digits' => [['phone' => '96012345'], 'phone', 'Enter a 7-digit Maldivian phone number, like 7771234 or +960 7771234.'],
            'blank national ID' => [['national_id_no' => ''], 'national_id_no', 'Enter your national ID card number.'],
            'passport number instead of ID' => [['national_id_no' => 'LA1234567'], 'national_id_no', 'Enter your ID card number as the letter A and 6 digits, like A123456.'],
            'blank address' => [['address' => ''], 'address', 'Enter your address.'],
            'address over 500 characters' => [['address' => str_repeat('b', 501)], 'address', 'Your address must be 500 characters or fewer.'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_a_clear_message(array $change, string $field, string $message): void
    {
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->from('/investor/profile')
            ->put('/investor/profile', $this->validInput($change))
            ->assertRedirect('/investor/profile')
            ->assertSessionHasErrors([$field => $message]);

        $this->assertNull($investor->fresh()->investorProfile);
    }

    public function test_the_acknowledgement_cannot_be_set_through_the_profile_form(): void
    {
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->put('/investor/profile', $this->validInput([
            'risk_acknowledged_at' => now()->toDateTimeString(),
            'shariah_only' => '1',
        ]));

        $this->assertNull($investor->fresh()->investorProfile->risk_acknowledged_at);
        $this->assertFalse($investor->fresh()->investorProfile->shariah_only);
    }

    public function test_a_verified_investors_profile_is_locked(): void
    {
        $investor = User::factory()->investor()->verified()->create();
        InvestorProfile::factory()->for($investor)->create(['address' => 'Original address']);

        $this->actingAs($investor)->put('/investor/profile', $this->validInput())->assertForbidden();

        $this->assertSame('Original address', $investor->fresh()->investorProfile->address);
    }

    public function test_a_rejected_investor_who_fixes_their_profile_goes_back_to_the_review_queue(): void
    {
        $investor = User::factory()->investor()->create();
        $investor->forceFill(['status' => 'rejected', 'status_reason' => 'Name does not match ID.'])->save();

        $this->actingAs($investor)->put('/investor/profile', $this->validInput());

        $this->assertSame('pending_verification', $investor->fresh()->status);
    }

    public function test_an_investor_can_upload_an_id_document(): void
    {
        Storage::fake(KycDocumentStore::DISK);
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->post('/investor/documents', ['document_type' => 'national_id', 'file' => $this->pdfFile()])
            ->assertRedirect('/investor/documents')
            ->assertSessionHasNoErrors();

        $this->assertSame($investor->id, KycDocument::sole()->user_id);
        $this->actingAs($investor)->get('/investor/documents')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Documents/Index')
                ->has('documents', 1)
                ->has('documentTypes', 2)
                ->where('uploadUrl', route('investor.documents.store'))
            );
    }

    public function test_an_investor_cannot_upload_a_business_registration(): void
    {
        Storage::fake(KycDocumentStore::DISK);
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->post('/investor/documents', ['document_type' => 'business_registration', 'file' => $this->pdfFile()])
            ->assertSessionHasErrors(['document_type' => 'Choose what kind of document this is from the list.']);

        $this->assertSame(0, KycDocument::count());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function otherRolesOnInvestorRoutes(): array
    {
        return [
            'borrower opens the investor profile' => ['borrower', 'get', '/investor/profile'],
            'borrower saves an investor profile' => ['borrower', 'put', '/investor/profile'],
            'admin saves an investor profile' => ['admin', 'put', '/investor/profile'],
            'borrower opens investor documents' => ['borrower', 'get', '/investor/documents'],
            'borrower uploads as an investor' => ['borrower', 'post', '/investor/documents'],
        ];
    }

    #[DataProvider('otherRolesOnInvestorRoutes')]
    public function test_only_investors_can_use_investor_profile_and_document_routes(string $role, string $method, string $url): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)->{$method}($url, $this->validInput())->assertForbidden();
        $this->assertSame(0, InvestorProfile::count());
    }
}
