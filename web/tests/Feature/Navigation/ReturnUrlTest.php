<?php

namespace Tests\Feature\Navigation;

use App\Models\Campaign;
use App\Models\User;
use App\Support\ReturnUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesOnboardedUsers;
use Tests\TestCase;

/**
 * PB-54: return navigation. Links pass ?return_to=<the page they came from>; back links,
 * breadcrumbs and post-decision redirects go there. Only internal paths of this app are
 * accepted, so a crafted link can't send anyone to another site.
 */
class ReturnUrlTest extends TestCase
{
    use CreatesOnboardedUsers, RefreshDatabase;

    /**
     * Values that must be refused: [return_to].
     *
     * @return array<string, array{0: mixed}>
     */
    public static function refused(): array
    {
        return [
            'blank' => [''],
            'null' => [null],
            'an array' => [['/admin']],
            'full external URL' => ['https://evil.example/login'],
            'external URL over http' => ['http://evil.example'],
            'protocol-relative URL' => ['//evil.example/admin'],
            'backslash trick' => ['/\\evil.example'],
            'double backslash' => ['\\\\evil.example'],
            'javascript: URL' => ['javascript:alert(1)'],
            'data: URL' => ['data:text/html,<script>alert(1)</script>'],
            'relative path without a slash' => ['admin/campaigns'],
            'leading space' => [' /admin/campaigns'],
            'encoded second slash' => ['/%2F%2Fevil.example'],
            'encoded backslash' => ['/%5Cevil.example'],
            'header injection' => ["/admin/campaigns\r\nLocation: https://evil.example"],
            'encoded header injection' => ['/admin/campaigns%0D%0ALocation:evil'],
            'user info before a host' => ['/@evil.example'],
            'path that is not a page' => ['/no-such-page'],
            'too long' => ['/admin/campaigns?search='.str_repeat('a', ReturnUrl::MAX_LENGTH)],
        ];
    }

    #[DataProvider('refused')]
    public function test_anything_but_an_internal_page_is_refused(mixed $value): void
    {
        $this->assertNull(ReturnUrl::sanitize($value));
    }

    /**
     * [return_to, what is kept]
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function accepted(): array
    {
        return [
            'a list' => ['/admin/campaigns', '/admin/campaigns'],
            'a list with its tab and filters' => ['/admin/campaigns?tab=live&search=van', '/admin/campaigns?tab=live&search=van'],
            'a detail page' => ['/admin/verifications/12', '/admin/verifications/12'],
            'a page that itself has a return_to' => ['/admin/campaigns/7?return_to=%2Fadmin%2Fcampaigns%3Ftab%3Dlive', '/admin/campaigns/7?return_to=%2Fadmin%2Fcampaigns%3Ftab%3Dlive'],
            'a fragment is dropped' => ['/borrower/campaigns#top', '/borrower/campaigns'],
        ];
    }

    #[DataProvider('accepted')]
    public function test_internal_pages_are_accepted(string $value, string $expected): void
    {
        $this->assertSame($expected, ReturnUrl::sanitize($value));
    }

    public function test_campaign_review_to_verification_record_and_back_to_that_review(): void
    {
        $admin = User::factory()->admin()->create();
        $borrower = $this->onboardedBorrower(['status' => 'verified']);
        $campaign = Campaign::factory()->submitted()->for($borrower, 'borrower')->create();
        $reviewUrl = "/admin/campaigns/{$campaign->id}?return_to=".rawurlencode('/admin/campaigns?tab=review&search=van');

        // The review page came from the filtered list: its back link goes to that list.
        $this->actingAs($admin)->get($reviewUrl)
            ->assertInertia(fn (Assert $page) => $page
                ->where('back.url', '/admin/campaigns?tab=review&search=van')
                ->where('back.label', 'Back to campaigns')
                ->where('back.fromLink', true)
            );

        // The verification record opened from that review goes back to the review, which still
        // knows its own way back to the filtered list.
        $this->actingAs($admin)->get("/admin/verifications/{$borrower->id}?return_to=".rawurlencode($reviewUrl))
            ->assertInertia(fn (Assert $page) => $page
                ->where('back.url', $reviewUrl)
                ->where('back.label', 'Back to the campaign review')
                ->where('back.crumb', 'Campaign review')
            );
    }

    public function test_without_a_return_to_the_back_link_goes_to_the_normal_parent(): void
    {
        $admin = User::factory()->admin()->create();
        $borrower = $this->onboardedBorrower();

        $this->actingAs($admin)->get("/admin/verifications/{$borrower->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('back.url', '/admin/verifications')
                ->where('back.label', 'Back to the verification queue')
                ->where('back.fromLink', false)
            );
    }

    public function test_an_external_return_to_falls_back_to_the_normal_parent(): void
    {
        $borrower = User::factory()->borrower()->verified()->create();
        $campaign = Campaign::factory()->submitted()->for($borrower, 'borrower')->create();

        $this->actingAs($borrower)->get("/borrower/campaigns/{$campaign->id}?return_to=".rawurlencode('https://evil.example'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('back.url', '/borrower/campaigns')
                ->where('back.label', 'Back to your requests')
                ->where('back.fromLink', false)
            );
    }

    public function test_after_a_decision_the_admin_returns_to_the_filtered_list(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $campaign = Campaign::factory()->submitted()->create();

        $this->actingAs($admin)->post("/admin/campaigns/{$campaign->id}/decision", [
            'decision' => 'reject',
            'reason' => 'The purpose is not described clearly enough.',
            'return_to' => '/admin/campaigns?tab=review&purpose=growth',
        ])->assertRedirect('/admin/campaigns?tab=review&purpose=growth');
    }

    public function test_a_decision_with_an_external_return_to_goes_to_the_list_instead(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $applicant = $this->onboardedBorrower();

        $this->actingAs($admin)->post("/admin/verifications/{$applicant->id}/decision", [
            'decision' => 'approve',
            'return_to' => '//evil.example',
        ])->assertRedirect('/admin/verifications');
    }
}
