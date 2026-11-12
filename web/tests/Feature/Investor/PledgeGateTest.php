<?php

namespace Tests\Feature\Investor;

use App\Models\InvestorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-10: an investor cannot pledge until the suitability acknowledgement is accepted.
 *
 * The pledge route itself is PB-19. It will be protected with ->can('pledge'), so these tests
 * check the 'pledge' gate directly and through a route that uses it the same way.
 */
class PledgeGateTest extends TestCase
{
    use RefreshDatabase;

    private const NEEDS_ACKNOWLEDGEMENT = 'Accept the risk acknowledgement before you pledge.';

    private const NEEDS_VERIFICATION = 'Your account must be verified before you can pledge.';

    private static function investor(bool $verified, bool $hasProfile, bool $acknowledged): User
    {
        $factory = User::factory()->investor();
        $user = ($verified ? $factory->verified() : $factory)->create();

        if ($hasProfile) {
            InvestorProfile::factory()->for($user)->create(['risk_acknowledged_at' => $acknowledged ? now() : null]);
        }

        return $user;
    }

    /**
     * [verified, has profile, acknowledged, allowed, reason when refused]
     *
     * @return array<string, array{0: bool, 1: bool, 2: bool, 3: bool, 4: ?string}>
     */
    public static function investorStates(): array
    {
        return [
            'pending, no profile' => [false, false, false, false, self::NEEDS_ACKNOWLEDGEMENT],
            'pending, not acknowledged' => [false, true, false, false, self::NEEDS_ACKNOWLEDGEMENT],
            'verified, not acknowledged' => [true, true, false, false, self::NEEDS_ACKNOWLEDGEMENT],
            'pending, acknowledged' => [false, true, true, false, self::NEEDS_VERIFICATION],
            'verified and acknowledged' => [true, true, true, true, null],
        ];
    }

    #[DataProvider('investorStates')]
    public function test_the_pledge_gate_requires_the_acknowledgement_and_verification(
        bool $verified, bool $hasProfile, bool $acknowledged, bool $allowed, ?string $reason,
    ): void {
        $investor = self::investor($verified, $hasProfile, $acknowledged);

        $answer = Gate::forUser($investor)->inspect('pledge');

        $this->assertSame($allowed, $answer->allowed());
        $this->assertSame($reason, $answer->message());
    }

    public function test_borrowers_and_admins_can_never_pledge(): void
    {
        foreach (['borrower', 'admin'] as $role) {
            $answer = Gate::forUser(User::factory()->{$role}()->verified()->create())->inspect('pledge');

            $this->assertTrue($answer->denied());
            $this->assertSame('Only investors can pledge.', $answer->message());
        }
    }

    public function test_a_route_protected_by_the_gate_refuses_until_the_acknowledgement_is_accepted(): void
    {
        // Test-only stand-in for PB-19's pledge route, protected the same way.
        Route::middleware(['web', 'auth', 'can:pledge'])->post('/test-only/pledge', fn () => 'pledged');

        $investor = self::investor(verified: true, hasProfile: true, acknowledged: false);
        $this->actingAs($investor)->post('/test-only/pledge')->assertForbidden();

        // Accept through the real form, then try again.
        $this->actingAs($investor)->post('/investor/acknowledgement', ['accepted' => '1']);
        $this->actingAs($investor->fresh())->post('/test-only/pledge')->assertOk()->assertSee('pledged');
    }

    public function test_the_investor_portal_shows_why_pledging_is_locked(): void
    {
        $investor = self::investor(verified: true, hasProfile: true, acknowledged: false);

        $this->actingAs($investor)->get('/investor')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Investor/Portal')
                ->where('pledge.allowed', false)
                ->where('pledge.reason', self::NEEDS_ACKNOWLEDGEMENT)
                ->where('riskAcknowledged', false)
            );
    }

    public function test_the_investor_portal_shows_pledging_unlocked_once_everything_is_done(): void
    {
        $investor = self::investor(verified: true, hasProfile: true, acknowledged: true);

        $this->actingAs($investor)->get('/investor')
            ->assertInertia(fn (Assert $page) => $page
                ->where('pledge.allowed', true)
                ->where('riskAcknowledged', true)
            );
    }
}
