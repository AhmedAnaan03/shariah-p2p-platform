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
 * PB-10: the investor completes their profile (and uploads an ID document for PB-11). Since
 * PB-53 these are steps of the onboarding wizard; the address has its own step.
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
        ], $overrides);
    }

    public function test_a_new_investor_sees_an_empty_profile_form(): void
    {
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->get('/investor/onboarding/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Profile')
                ->where('profile', null)
                ->where('wizard.locked', false)
            );
    }

    public function test_a_valid_profile_is_saved(): void
    {
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->put('/investor/onboarding/profile', $this->validInput())
            ->assertRedirect('/investor/onboarding/address')
            ->assertSessionHas('status', 'Your details have been saved.');

        $profile = $investor->fresh()->investorProfile;
        $this->assertSame('+960 9601234', $profile->phone);
        $this->assertSame('A246810', $profile->national_id_no);
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
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_a_clear_message(array $change, string $field, string $message): void
    {
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->from('/investor/onboarding/profile')
            ->put('/investor/onboarding/profile', $this->validInput($change))
            ->assertRedirect('/investor/onboarding/profile')
            ->assertSessionHasErrors([$field => $message]);

        $this->assertNull($investor->fresh()->investorProfile);
    }

    public function test_the_acknowledgement_cannot_be_set_through_the_profile_form(): void
    {
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->put('/investor/onboarding/profile', $this->validInput([
            'risk_acknowledged_at' => now()->toDateTimeString(),
            'shariah_only' => '1',
        ]));

        $this->assertNull($investor->fresh()->investorProfile->risk_acknowledged_at);
        $this->assertFalse($investor->fresh()->investorProfile->shariah_only);
    }

    public function test_a_verified_investors_profile_is_locked(): void
    {
        $investor = User::factory()->investor()->verified()->create();
        InvestorProfile::factory()->for($investor)->create(['phone' => '+960 7000001']);

        $this->actingAs($investor)->put('/investor/onboarding/profile', $this->validInput())->assertForbidden();

        $this->assertSame('+960 7000001', $investor->fresh()->investorProfile->phone);
    }

    public function test_a_rejected_investor_who_fixes_their_profile_stays_rejected_until_they_submit_again(): void
    {
        $investor = User::factory()->investor()->create();
        $investor->forceFill(['status' => 'rejected', 'status_reason' => 'Name does not match ID.'])->save();

        $this->actingAs($investor)->put('/investor/onboarding/profile', $this->validInput());

        $this->assertSame('rejected', $investor->fresh()->status);
    }

    public function test_an_investor_can_upload_an_id_document(): void
    {
        Storage::fake(KycDocumentStore::DISK);
        $investor = User::factory()->investor()->create();
        InvestorProfile::factory()->for($investor)->create();

        $this->actingAs($investor)->post('/investor/onboarding/documents', ['document_type' => 'national_id', 'file' => $this->pdfFile()])
            ->assertRedirect('/investor/onboarding/documents')
            ->assertSessionHasNoErrors();

        $this->assertSame($investor->id, KycDocument::sole()->user_id);
        $this->actingAs($investor)->get('/investor/onboarding/documents')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Documents')
                ->has('slots', 2)
                ->where('slots.0.type', 'national_id')
                ->where('slots.0.document.original_name', 'id-card.pdf')
                ->where('slots.1.document', null)
                ->where('uploadUrl', route('investor.onboarding.documents.upload'))
            );
    }

    public function test_an_investor_cannot_upload_a_business_registration(): void
    {
        Storage::fake(KycDocumentStore::DISK);
        $investor = User::factory()->investor()->create();
        InvestorProfile::factory()->for($investor)->create();

        $this->actingAs($investor)->post('/investor/onboarding/documents', ['document_type' => 'business_registration', 'file' => $this->pdfFile()])
            ->assertSessionHasErrors(['document_type' => 'Upload into one of the document slots shown. Other documents are not needed.']);

        $this->assertSame(0, KycDocument::count());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function otherRolesOnInvestorRoutes(): array
    {
        return [
            'borrower opens the investor profile' => ['borrower', 'get', '/investor/onboarding/profile'],
            'borrower saves an investor profile' => ['borrower', 'put', '/investor/onboarding/profile'],
            'admin saves an investor profile' => ['admin', 'put', '/investor/onboarding/profile'],
            'borrower opens investor documents' => ['borrower', 'get', '/investor/onboarding/documents'],
            'borrower uploads as an investor' => ['borrower', 'post', '/investor/onboarding/documents'],
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
