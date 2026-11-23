<?php

namespace Tests\Feature\Onboarding;

use App\Models\AuditLog;
use App\Models\BorrowerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesOnboardedUsers;
use Tests\TestCase;

/**
 * PB-58: the borrower's financial profile, an onboarding step verified by the administrator with
 * KYC. Monthly income is asked (annual = × 12 for the model); a verified borrower who changes it
 * goes back into verification.
 */
class FinancialProfileStepTest extends TestCase
{
    use CreatesOnboardedUsers, RefreshDatabase;

    /**
     * A valid financial profile. Each test changes only what it is about.
     *
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'monthly_income' => '40000',
            'monthly_debt_repayments' => '6000',
            'employment_years' => '7',
            'housing' => 'own_with_home_loan',
            'open_credit_lines' => '3',
            'late_payments_2y' => '1',
            'public_records' => '0',
        ], $overrides);
    }

    /**
     * A borrower at the finance step: details and address saved, finances not yet.
     */
    private function borrowerAtFinances(string $type = 'individual'): User
    {
        $borrower = User::factory()->borrower()->create();
        ($type === 'sme' ? BorrowerProfile::factory() : BorrowerProfile::factory()->individual())
            ->withoutFinances()->for($borrower)->create();

        return $borrower;
    }

    private function save(User $borrower, array $input)
    {
        return $this->actingAs($borrower)->from('/borrower/onboarding/finances')->put('/borrower/onboarding/finances', $input);
    }

    public function test_the_finance_step_comes_after_the_address_and_asks_for_monthly_income(): void
    {
        $borrower = $this->borrowerAtFinances();

        $this->actingAs($borrower)->get('/borrower/onboarding')->assertRedirect('/borrower/onboarding/finances');
        $this->actingAs($borrower)->get('/borrower/onboarding/finances')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Finances')
                ->where('finances.monthly_income', '')
                ->where('isSme', false)
                ->has('housing', 4)
                ->has('employmentYears', 11)
                ->where('verified', false)
                ->where('wizard.steps.2.key', 'finances')
            );
    }

    public function test_a_valid_profile_is_saved_and_the_wizard_moves_on(): void
    {
        $borrower = $this->borrowerAtFinances();

        $this->save($borrower, $this->input(['monthly_income' => '40,000.50']))
            ->assertRedirect('/borrower/onboarding/documents')
            ->assertSessionHas('status', 'Your financial profile has been saved.');

        $profile = $borrower->fresh()->borrowerProfile;
        $this->assertSame('40000.50', $profile->monthly_income, 'Separators typed by hand are removed.');
        $this->assertSame('6000.00', $profile->monthly_debt_repayments);
        $this->assertSame(7, $profile->employment_years);
        $this->assertSame('own_with_home_loan', $profile->housing);
        $this->assertSame([3, 1, 0], [$profile->open_credit_lines, $profile->late_payments_2y, $profile->public_records]);
        $this->assertTrue($profile->hasFinancialProfile());
    }

    /**
     * Blank and invalid inputs: [changed input, field, expected message].
     *
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidInputs(): array
    {
        $count = 'Enter a whole number from 0 to 99. Enter 0 if none.';
        $employment = 'Choose how long you have been in your job or running the business.';

        return [
            'income blank' => [['monthly_income' => ''], 'monthly_income', 'Enter your monthly income before tax.'],
            'income zero' => [['monthly_income' => '0'], 'monthly_income', 'Your monthly income must be more than 0.'],
            'income negative' => [['monthly_income' => '-100'], 'monthly_income', 'Your monthly income must be more than 0.'],
            'income in words' => [['monthly_income' => 'a lot'], 'monthly_income', 'Enter your monthly income as a number, like 40000.'],
            'income with 3 decimals' => [['monthly_income' => '1000.555'], 'monthly_income', 'Enter your monthly income with at most 2 decimal places.'],
            'income too large' => [['monthly_income' => '10000001'], 'monthly_income', 'Check your monthly income: it must be MVR 10,000,000 or less.'],
            'repayments blank' => [['monthly_debt_repayments' => ''], 'monthly_debt_repayments', 'Enter what you repay each month on existing loans and cards. Enter 0 if nothing.'],
            'repayments negative' => [['monthly_debt_repayments' => '-50'], 'monthly_debt_repayments', 'Monthly repayments can\'t be negative. Enter 0 if nothing.'],
            'employment blank' => [['employment_years' => ''], 'employment_years', $employment],
            'employment 11 years' => [['employment_years' => '11'], 'employment_years', $employment],
            'housing blank' => [['housing' => ''], 'housing', 'Choose your housing situation.'],
            'housing not offered' => [['housing' => 'castle'], 'housing', 'Choose your housing situation from the list.'],
            'open credit lines blank' => [['open_credit_lines' => ''], 'open_credit_lines', 'Enter how many loans, financing facilities and credit cards you have open. Enter 0 if none.'],
            'open credit lines 1.5' => [['open_credit_lines' => '1.5'], 'open_credit_lines', $count],
            'late payments 100' => [['late_payments_2y' => '100'], 'late_payments_2y', $count],
            'public records text' => [['public_records' => 'none'], 'public_records', $count],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_refused_with_a_clear_message(array $change, string $field, string $message): void
    {
        $borrower = $this->borrowerAtFinances();

        $this->save($borrower, $this->input($change))
            ->assertRedirect('/borrower/onboarding/finances')
            ->assertSessionHasErrors([$field => $message]);

        $this->assertNull($borrower->fresh()->borrowerProfile->monthly_income, 'Nothing is saved.');
    }

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public static function boundaries(): array
    {
        return [
            'smallest income' => [['monthly_income' => '0.01']],
            'largest income' => [['monthly_income' => '10000000']],
            'no repayments' => [['monthly_debt_repayments' => '0']],
            'less than a year' => [['employment_years' => '0']],
            'ten years or more' => [['employment_years' => '10']],
            'family home' => [['housing' => 'family_home']],
            'counts at 0' => [['open_credit_lines' => '0', 'late_payments_2y' => '0', 'public_records' => '0']],
            'counts at 99' => [['open_credit_lines' => '99', 'late_payments_2y' => '99', 'public_records' => '99']],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_boundary_values_are_accepted(array $change): void
    {
        $this->save($this->borrowerAtFinances(), $this->input($change))->assertSessionHasNoErrors();
    }

    public function test_without_a_financial_profile_the_account_cannot_be_submitted(): void
    {
        $borrower = $this->borrowerAtFinances();
        $this->fillSlots($borrower);

        $this->actingAs($borrower)->post('/borrower/onboarding/submit')->assertRedirect('/borrower/onboarding/finances');
        $this->assertNull($borrower->fresh()->verification_submitted_at);
    }

    public function test_a_verified_borrower_who_changes_it_goes_back_into_verification(): void
    {
        Carbon::setTestNow('2026-11-23 09:00:00');
        $borrower = $this->onboardedBorrower(['status' => 'verified']);
        $before = $borrower->borrowerProfile->financialProfile();

        $this->actingAs($borrower)->get('/borrower/onboarding/finances')
            ->assertInertia(fn (Assert $page) => $page->where('wizard.locked', false)->where('verified', true));

        $this->save($borrower, $this->input(['monthly_income' => '55000']))
            ->assertRedirect('/borrower/onboarding/review')
            ->assertSessionHas('status', 'Your financial profile has been saved. Because it changed, an administrator will check it again before you can submit new financing requests.');

        $borrower->refresh();
        $this->assertSame('pending_verification', $borrower->status);
        $this->assertTrue($borrower->verification_submitted_at->equalTo(now()), 'Straight back into the queue.');
        $this->assertSame('55000.00', $borrower->borrowerProfile->monthly_income);

        $entry = AuditLog::sole();
        $this->assertSame('user.financial_profile_changed', $entry->action);
        $this->assertEquals($before['monthly_income'], $entry->details['before']['monthly_income']);

        $this->actingAs($borrower)->post('/borrower/campaigns', [])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/verifications')
            ->assertInertia(fn (Assert $page) => $page->where('applicants.0.id', $borrower->id));
    }

    public function test_saving_the_same_figures_keeps_a_verified_borrower_verified(): void
    {
        $borrower = $this->onboardedBorrower(['status' => 'verified']);
        $same = array_map(fn ($value) => (string) $value, $borrower->borrowerProfile->financialProfile());

        $this->save($borrower, [...$same, 'return_to' => '/borrower/campaigns/create'])
            ->assertRedirect('/borrower/campaigns/create');

        $this->assertSame('verified', $borrower->fresh()->status);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_verified_borrower_still_cannot_change_their_other_details(): void
    {
        $borrower = $this->onboardedBorrower(['status' => 'verified']);

        $this->actingAs($borrower)->get('/borrower/onboarding/profile')
            ->assertInertia(fn (Assert $page) => $page->where('wizard.locked', true));
        $this->actingAs($borrower)->put('/borrower/onboarding/profile', [])->assertForbidden();
    }

    public function test_a_rejected_borrower_stays_rejected_until_they_submit_again(): void
    {
        $borrower = $this->onboardedBorrower(['status' => 'rejected', 'status_reason' => 'Salary slip unreadable.']);

        $this->save($borrower, $this->input(['monthly_income' => '30000']))->assertSessionHasNoErrors();

        $this->assertSame('rejected', $borrower->fresh()->status);
    }

    public function test_a_suspended_borrower_cannot_change_it(): void
    {
        $borrower = $this->onboardedBorrower(['status' => 'suspended']);

        $this->save($borrower, $this->input(['monthly_income' => '30000']))->assertForbidden();
    }

    public function test_the_administrator_sees_the_financial_profile_on_the_verification_record(): void
    {
        $borrower = $this->onboardedBorrower();
        $borrower->borrowerProfile->update(['monthly_income' => 25_000]);

        $this->actingAs(User::factory()->admin()->create())->get("/admin/verifications/{$borrower->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile', fn ($rows) => collect($rows)->pluck('value', 'label')->only(['Monthly income', 'Annual income (monthly × 12)'])->all() === [
                    'Monthly income' => 'MVR 25,000.00',
                    'Annual income (monthly × 12)' => 'MVR 300,000.00',
                ])
                ->where('profile', fn ($rows) => collect($rows)->pluck('label')->contains('Repayments 30 or more days late in the past 2 years'))
            );
    }

    public function test_an_sme_is_asked_for_average_monthly_revenue(): void
    {
        $this->actingAs($this->borrowerAtFinances('sme'))->get('/borrower/onboarding/finances')
            ->assertInertia(fn (Assert $page) => $page->where('isSme', true));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function otherRoles(): array
    {
        return ['investor' => ['investor'], 'admin' => ['admin']];
    }

    #[DataProvider('otherRoles')]
    public function test_only_borrowers_have_a_financial_profile(string $role): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)->get('/borrower/onboarding/finances')->assertForbidden();
        $this->actingAs($user)->put('/borrower/onboarding/finances', $this->input())->assertForbidden();
        $this->actingAs(User::factory()->investor()->create())->get('/investor/onboarding/finances')->assertNotFound();
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->put('/borrower/onboarding/finances', $this->input())->assertRedirect('/login');
    }
}
