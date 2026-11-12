<?php

namespace Tests\Feature\Investor;

use App\Models\AuditLog;
use App\Models\InvestorProfile;
use App\Models\User;
use App\Support\RiskAcknowledgement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-10: the acknowledgement explains capital-at-risk in plain language, and accepting it is
 * recorded.
 */
class RiskAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    private function investorWithProfile(): User
    {
        return InvestorProfile::factory()->create()->user;
    }

    public function test_the_page_shows_the_acknowledgement_text_from_the_server(): void
    {
        $investor = $this->investorWithProfile();

        $this->actingAs($investor)->get('/investor/acknowledgement')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Investor/Acknowledgement')
                ->where('acknowledgement.version', RiskAcknowledgement::VERSION)
                ->where('acknowledgement.title', 'Before you invest: understand the risks')
                ->has('acknowledgement.points', count(RiskAcknowledgement::POINTS))
                ->where('hasProfile', true)
                ->where('acknowledgedAt', null)
            );
    }

    public function test_the_text_explains_capital_at_risk_in_plain_language(): void
    {
        $text = collect(RiskAcknowledgement::POINTS)
            ->map(fn (array $point) => $point['title'].' '.$point['text'])
            ->implode(' ').' '.RiskAcknowledgement::CONFIRMATION;

        // The facts an investor must be told.
        $this->assertStringContainsString('lose some or all of the money', $text);
        $this->assertStringContainsString('not guaranteed', $text);
        $this->assertStringContainsString('cannot take your pledge back', $text);
        $this->assertStringContainsString('not covered by any government or deposit guarantee scheme', $text);
        $this->assertStringContainsString('afford to lose', $text);

        // Plain language: no financial jargon that needs a definition.
        foreach (['capital impairment', 'illiquid', 'principal', 'counterparty', 'default risk', 'interest'] as $jargon) {
            $this->assertStringNotContainsStringIgnoringCase($jargon, $text, "Avoid the term '{$jargon}'.");
        }

        // Short sentences: none over 30 words.
        foreach (preg_split('/(?<=[.!?])\s+/', $text) as $sentence) {
            $this->assertLessThanOrEqual(30, str_word_count($sentence), "Too long: {$sentence}");
        }
    }

    public function test_accepting_records_the_time_and_writes_an_audit_entry(): void
    {
        Carbon::setTestNow('2026-11-12 10:30:00');
        $investor = $this->investorWithProfile();

        $this->actingAs($investor)->post('/investor/acknowledgement', ['accepted' => '1'])
            ->assertRedirect('/investor')
            ->assertSessionHas('status', 'Thank you. You have accepted the risk acknowledgement.');

        $profile = $investor->fresh()->investorProfile;
        $this->assertTrue($profile->risk_acknowledged_at->equalTo('2026-11-12 10:30:00'));

        $entry = AuditLog::sole();
        $this->assertSame('investor.risk_acknowledged', $entry->action);
        $this->assertSame($investor->id, $entry->actor_id);
        $this->assertSame(InvestorProfile::class, $entry->subject_type);
        $this->assertSame($profile->id, $entry->subject_id);
        $this->assertSame(['text_version' => RiskAcknowledgement::VERSION], $entry->details);
        $this->assertSame('127.0.0.1', $entry->ip_address);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function notAccepted(): array
    {
        return [
            'box not ticked (field missing)' => [[]],
            'box sent empty' => [['accepted' => '']],
            'false' => [['accepted' => '0']],
            'no' => [['accepted' => 'no']],
        ];
    }

    #[DataProvider('notAccepted')]
    public function test_the_box_must_be_ticked(array $input): void
    {
        $investor = $this->investorWithProfile();

        $this->actingAs($investor)->from('/investor/acknowledgement')
            ->post('/investor/acknowledgement', $input)
            ->assertRedirect('/investor/acknowledgement')
            ->assertSessionHasErrors(['accepted' => 'Tick the box to confirm you have read and understood the risks.']);

        $this->assertNull($investor->fresh()->investorProfile->risk_acknowledged_at);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_accepting_again_keeps_the_first_date_and_logs_once(): void
    {
        Carbon::setTestNow('2026-11-12 09:00:00');
        $investor = $this->investorWithProfile();
        $this->actingAs($investor)->post('/investor/acknowledgement', ['accepted' => '1']);

        Carbon::setTestNow('2026-11-20 09:00:00');
        $this->actingAs($investor)->post('/investor/acknowledgement', ['accepted' => '1']);

        $this->assertTrue($investor->fresh()->investorProfile->risk_acknowledged_at->equalTo('2026-11-12 09:00:00'));
        $this->assertSame(1, AuditLog::count());
    }

    public function test_an_investor_without_a_profile_is_sent_to_complete_it_first(): void
    {
        $investor = User::factory()->investor()->create();

        $this->actingAs($investor)->get('/investor/acknowledgement')
            ->assertInertia(fn (Assert $page) => $page->where('hasProfile', false));

        $this->actingAs($investor)->post('/investor/acknowledgement', ['accepted' => '1'])
            ->assertRedirect('/investor/profile')
            ->assertSessionHas('status', 'Complete your profile first, then accept the risk acknowledgement.');

        $this->assertSame(0, AuditLog::count());
    }

    public function test_only_investors_can_open_or_accept_the_acknowledgement(): void
    {
        foreach (['borrower', 'admin'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)->get('/investor/acknowledgement')->assertForbidden();
            $this->actingAs($user)->post('/investor/acknowledgement', ['accepted' => '1'])->assertForbidden();
        }

        $this->assertSame(0, AuditLog::count());
    }
}
