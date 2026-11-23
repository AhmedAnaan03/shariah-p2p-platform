<?php

namespace Tests\Feature\Onboarding;

use App\Models\BorrowerProfile;
use App\Models\InvestorProfile;
use App\Models\Island;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesOnboardedUsers;
use Tests\TestCase;

/**
 * PB-53: the onboarding wizard's step order, resuming where the user left off, staying in the
 * flow after every save, revisiting earlier steps, the lock after verification, and role
 * access on every new route.
 */
class OnboardingFlowTest extends TestCase
{
    use CreatesOnboardedUsers, RefreshDatabase;

    public function test_borrowers_and_investors_have_their_own_steps_in_order(): void
    {
        $this->actingAs(User::factory()->borrower()->create())->get('/borrower/onboarding/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->where('wizard.steps', fn ($steps) => collect($steps)->pluck('key')->all() === ['profile', 'address', 'documents', 'review'])
                ->where('wizard.step', 'profile')
                ->where('wizard.backUrl', null)
            );

        $this->actingAs(User::factory()->investor()->create())->get('/investor/onboarding/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->where('wizard.steps', fn ($steps) => collect($steps)->pluck('key')->all() === ['profile', 'address', 'documents', 'risk', 'review'])
                ->where('wizard.steps.3.title', 'Risk acknowledgement')
            );
    }

    /**
     * Where /onboarding resumes: [how far the user got, expected step].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function resumePoints(): array
    {
        return [
            'nothing saved yet (blank)' => ['nothing', 'profile'],
            'details saved, no address' => ['profile', 'address'],
            'address saved, documents missing' => ['address', 'documents'],
            'documents uploaded, not submitted' => ['documents', 'review'],
            'submitted' => ['submitted', 'review'],
        ];
    }

    #[DataProvider('resumePoints')]
    public function test_coming_back_resumes_at_the_first_incomplete_step(string $progress, string $expected): void
    {
        $borrower = $this->borrowerAt($progress);

        $this->actingAs($borrower)->get('/borrower/onboarding')->assertRedirect("/borrower/onboarding/{$expected}");
    }

    public function test_an_investor_resumes_at_the_risk_step_after_the_documents(): void
    {
        $investor = User::factory()->investor()->create();
        InvestorProfile::factory()->for($investor)->create();
        $this->fillSlots($investor);

        $this->actingAs($investor)->get('/investor/onboarding')->assertRedirect('/investor/onboarding/risk');
    }

    public function test_a_later_step_cannot_be_opened_before_the_earlier_ones_are_done(): void
    {
        $borrower = $this->borrowerAt('profile');

        $this->actingAs($borrower)->get('/borrower/onboarding/documents')->assertRedirect('/borrower/onboarding/address');
        $this->actingAs($borrower)->get('/borrower/onboarding/review')->assertRedirect('/borrower/onboarding/address');
    }

    public function test_a_save_for_a_step_not_reached_yet_goes_back_to_the_current_step(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->actingAs($borrower)->put('/borrower/onboarding/address', [
            'address_house' => 'H. Sea Breeze', 'address_road' => 'Majeedhee Magu', 'atoll_id' => 1, 'island_id' => 1, 'postcode' => '20026',
        ])->assertRedirect('/borrower/onboarding/profile');

        $this->assertNull($borrower->fresh()->borrowerProfile);
    }

    public function test_earlier_steps_can_be_reopened_and_edited_until_verification(): void
    {
        $borrower = $this->borrowerAt('submitted');

        $this->actingAs($borrower)->get('/borrower/onboarding/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('wizard.locked', false)
                ->where('wizard.steps.0.done', true)
                ->where('wizard.steps.2.href', route('borrower.onboarding.show', 'documents'))
            );

        $this->actingAs($borrower)->put('/borrower/onboarding/profile', [
            'borrower_type' => 'individual', 'phone' => '7771234', 'national_id_no' => 'A111111', 'declared_annual_income' => '90000',
        ])->assertSessionHasNoErrors()->assertRedirect('/borrower/onboarding/address');

        $this->assertSame('+960 7771234', $borrower->fresh()->borrowerProfile->phone);
    }

    public function test_every_save_moves_on_to_the_next_step_inside_the_wizard(): void
    {
        $investor = User::factory()->investor()->create();
        $this->actingAs($investor);

        $this->put('/investor/onboarding/profile', ['phone' => '9601234', 'national_id_no' => 'A246810'])
            ->assertRedirect('/investor/onboarding/address');

        $male = Island::where('name', 'Malé')->sole();
        $this->put('/investor/onboarding/address', [
            'address_house' => 'G. Coral View', 'address_road' => 'Nirolhu Magu', 'atoll_id' => $male->atoll_id, 'island_id' => $male->id, 'postcode' => '20026',
        ])->assertRedirect('/investor/onboarding/documents');

        $this->fillSlots($investor);
        $this->put('/investor/onboarding/documents')->assertRedirect('/investor/onboarding/risk');

        $this->post('/investor/onboarding/risk', ['accepted' => '1'])->assertRedirect('/investor/onboarding/review');

        $this->post('/investor/onboarding/submit')->assertRedirect('/investor/onboarding/review');
        $this->assertNotNull($investor->fresh()->verification_submitted_at);
    }

    public function test_a_verified_account_can_read_every_step_but_save_none(): void
    {
        $borrower = $this->onboardedBorrower(['status' => 'verified']);

        $this->actingAs($borrower)->get('/borrower/onboarding')->assertRedirect('/borrower/onboarding/review');
        $this->actingAs($borrower)->get('/borrower/onboarding/address')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('wizard.locked', true));

        $this->actingAs($borrower)->put('/borrower/onboarding/address', [])->assertForbidden();
        $this->actingAs($borrower)->put('/borrower/onboarding/profile', [])->assertForbidden();
        $this->actingAs($borrower)->post('/borrower/onboarding/documents', [])->assertForbidden();
        $this->actingAs($borrower)->post('/borrower/onboarding/submit')->assertForbidden();
    }

    public function test_the_risk_step_does_not_exist_for_borrowers(): void
    {
        $this->actingAs($this->borrowerAt('documents'))->get('/borrower/onboarding/risk')->assertNotFound();
        $this->actingAs($this->borrowerAt('documents'))->get('/borrower/onboarding/unknown')->assertNotFound();
    }

    public function test_the_old_profile_and_documents_addresses_open_the_matching_step(): void
    {
        $borrower = $this->borrowerAt('address');

        $this->actingAs($borrower)->get('/borrower/profile')->assertRedirect('/borrower/onboarding/profile');
        $this->actingAs($borrower)->get('/borrower/documents')->assertRedirect('/borrower/onboarding/documents');
    }

    public function test_the_portal_shows_the_wizard_steps_and_where_to_continue(): void
    {
        $borrower = $this->borrowerAt('profile');

        $this->actingAs($borrower)->get('/borrower')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Borrower/Portal')
                ->where('onboarding.continueUrl', route('borrower.onboarding.show', 'address'))
                ->where('onboarding.steps.0.done', true)
                ->where('onboarding.steps.1.done', false)
            );
    }

    /**
     * Every new route, for the role it must refuse: [role, method, url].
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function otherRoles(): array
    {
        $cases = [];
        foreach (['borrower' => 'investor', 'investor' => 'borrower'] as $portal => $intruder) {
            foreach ([
                ['get', "/{$portal}/onboarding"],
                ['get', "/{$portal}/onboarding/profile"],
                ['put', "/{$portal}/onboarding/profile"],
                ['put', "/{$portal}/onboarding/address"],
                ['post', "/{$portal}/onboarding/documents"],
                ['put', "/{$portal}/onboarding/documents"],
                ['post', "/{$portal}/onboarding/submit"],
            ] as [$method, $url]) {
                $cases["{$intruder} {$method} {$url}"] = [$intruder, $method, $url];
                $cases["admin {$method} {$url}"] = ['admin', $method, $url];
            }
        }
        $cases['borrower post /investor/onboarding/risk'] = ['borrower', 'post', '/investor/onboarding/risk'];
        $cases['admin post /investor/onboarding/risk'] = ['admin', 'post', '/investor/onboarding/risk'];

        return $cases;
    }

    #[DataProvider('otherRoles')]
    public function test_each_role_can_only_use_its_own_wizard(string $role, string $method, string $url): void
    {
        $this->actingAs(User::factory()->{$role}()->create())->{$method}($url)->assertForbidden();
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        foreach (['/borrower/onboarding', '/investor/onboarding/profile'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/borrower/onboarding/submit')->assertRedirect('/login');
    }

    /**
     * A borrower who has got as far as $progress.
     */
    private function borrowerAt(string $progress): User
    {
        if ($progress === 'documents') {
            return $this->onboardedBorrower(submitted: false);
        }
        if ($progress === 'submitted') {
            return $this->onboardedBorrower();
        }

        $borrower = User::factory()->borrower()->create();
        $profile = match ($progress) {
            'nothing' => null,
            'profile' => BorrowerProfile::factory()->individual()->withoutAddress(),
            'address' => BorrowerProfile::factory()->individual(),
        };
        $profile?->for($borrower)->create();

        return $borrower;
    }
}
