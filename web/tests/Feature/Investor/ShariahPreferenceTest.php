<?php

namespace Tests\Feature\Investor;

use App\Models\InvestorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-51: the top bar's Shariah-only switch saves the investor's choice through
 * PATCH /investor/preferences/shariah-only. Owner only, investors only, validated.
 */
class ShariahPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/investor/preferences/shariah-only';

    public function test_an_investor_can_turn_shariah_only_on(): void
    {
        $profile = InvestorProfile::factory()->create(['shariah_only' => false]);

        $this->actingAs($profile->user)->from('/investor')->patch(self::URL, ['shariah_only' => true])
            ->assertRedirect('/investor')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Shariah-only mode is on.');

        $this->assertTrue($profile->fresh()->shariah_only);
    }

    public function test_an_investor_can_turn_shariah_only_off(): void
    {
        $profile = InvestorProfile::factory()->create(['shariah_only' => true]);

        $this->actingAs($profile->user)->from('/investor/profile')->patch(self::URL, ['shariah_only' => false])
            ->assertRedirect('/investor/profile')
            ->assertSessionHas('status', 'Shariah-only mode is off.');

        $this->assertFalse($profile->fresh()->shariah_only);
    }

    public function test_a_verified_investor_can_still_change_it(): void
    {
        // Verification locks the KYC details, not preferences.
        $investor = User::factory()->investor()->verified()->create();
        $profile = InvestorProfile::factory()->for($investor)->create();

        $this->actingAs($investor)->patch(self::URL, ['shariah_only' => true])->assertSessionHasNoErrors();

        $this->assertTrue($profile->fresh()->shariah_only);
    }

    public function test_it_only_changes_the_signed_in_investors_own_profile(): void
    {
        $mine = InvestorProfile::factory()->create(['shariah_only' => false]);
        $theirs = InvestorProfile::factory()->create(['shariah_only' => false]);

        // Even with someone else's id in the body, the route has no way to address another user.
        $this->actingAs($mine->user)->patch(self::URL, ['shariah_only' => true, 'user_id' => $theirs->user_id]);

        $this->assertTrue($mine->fresh()->shariah_only);
        $this->assertFalse($theirs->fresh()->shariah_only);
    }

    public function test_an_investor_without_a_profile_is_told_to_complete_it_first(): void
    {
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->patch(self::URL, ['shariah_only' => true])
            ->assertSessionHasErrors(['shariah_only' => 'Complete your profile first, then choose Shariah-only mode.']);

        $this->assertNull($investor->fresh()->investorProfile, 'No half-empty profile is created.');
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'missing' => [[], 'Choose whether Shariah-only mode is on or off.'],
            'blank' => [['shariah_only' => ''], 'Choose whether Shariah-only mode is on or off.'],
            'a word' => [['shariah_only' => 'yes'], 'Shariah-only mode can only be on or off.'],
            'a number other than 0 or 1' => [['shariah_only' => 2], 'Shariah-only mode can only be on or off.'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_and_nothing_changes(array $input, string $message): void
    {
        $profile = InvestorProfile::factory()->create(['shariah_only' => false]);

        $this->actingAs($profile->user)->patch(self::URL, $input)
            ->assertSessionHasErrors(['shariah_only' => $message]);

        $this->assertFalse($profile->fresh()->shariah_only);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->patch(self::URL, ['shariah_only' => true])->assertRedirect('/login');
    }

    public function test_a_borrower_is_forbidden(): void
    {
        $this->actingAs(User::factory()->borrower()->create())
            ->patch(self::URL, ['shariah_only' => true])
            ->assertForbidden();
    }

    public function test_an_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patch(self::URL, ['shariah_only' => true])
            ->assertForbidden();
    }
}
