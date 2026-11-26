<?php

namespace Tests\Feature\Auth;

use App\Models\Campaign;
use App\Models\KycDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\KycDocumentStore;
use App\Services\PledgeService;
use App\Support\IntendedUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * Sign in, register, reset: the page a signed-out browser tried to open (the "intended" URL) is
 * used only if it is the signed-in user's to open; otherwise they go to their portal home. It is
 * never carried to a later sign-in, and forms sent after the session expired (Sign out, a
 * top-up) don't leave one behind. Before this, a borrower signing in after an investor's
 * expired session was sent to the investor's page and saw a 403 or 404 over the sign-in page.
 */
class IntendedUrlTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    private const PASSWORD = 'Secure#2026';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 10:00:00');
        Notification::fake();
    }

    private function user(string $role, bool $verified = true): User
    {
        $factory = User::factory()->{$role}();

        return ($verified ? $factory->verified() : $factory)->create(['password' => self::PASSWORD]);
    }

    private function withPassword(User $user): User
    {
        $user->forceFill(['password' => self::PASSWORD])->save();

        return $user;
    }

    private function signIn(User $user)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
    }

    /** A signed-out browser opens $path: 'auth' sends it to the sign-in page and remembers $path. */
    private function openWhileSignedOut(string $path): void
    {
        $this->assertGuest();
        $this->get($path)->assertRedirect('/login');
        $this->assertSame(url($path), session('url.intended'));
    }

    // ----- Signing in normally -----

    public function test_signing_in_with_nothing_intended_goes_to_the_portal_for_each_role(): void
    {
        foreach ([Role::BORROWER => '/borrower', Role::INVESTOR => '/investor', Role::ADMIN => '/admin'] as $role => $portal) {
            $this->signIn($this->user($role))->assertRedirect($portal);
            $this->post('/logout');
        }
    }

    // ----- A valid intended URL for the same user still works -----

    public function test_a_page_the_user_may_open_is_where_they_land(): void
    {
        $investor = $this->withPassword($this->investor());
        $this->openWhileSignedOut('/investor/wallet');

        $this->signIn($investor)->assertRedirect('/investor/wallet');
        $this->assertNull(session('url.intended'), 'Used once, then gone.');
        $this->get('/investor/wallet')->assertOk();
    }

    public function test_a_query_string_is_kept(): void
    {
        $investor = $this->withPassword($this->investor());
        $this->openWhileSignedOut('/investor/campaigns?page=2&search=solar'); // Laravel stores it sorted

        $this->signIn($investor)->assertRedirect('/investor/campaigns?page=2&search=solar');
    }

    public function test_a_borrower_returns_to_their_own_request_and_an_admin_to_the_review_page(): void
    {
        $campaign = Campaign::factory()->create();
        $borrower = $this->withPassword($campaign->borrower);
        $this->openWhileSignedOut("/borrower/campaigns/{$campaign->id}");
        $this->signIn($borrower)->assertRedirect("/borrower/campaigns/{$campaign->id}");
        $this->post('/logout');

        $applicant = $this->user(Role::BORROWER, verified: false);
        $this->openWhileSignedOut("/admin/verifications/{$applicant->id}");
        $this->signIn($this->user(Role::ADMIN))->assertRedirect("/admin/verifications/{$applicant->id}");
    }

    public function test_an_investor_who_pledged_returns_to_the_funded_campaign(): void
    {
        $investor = $this->withPassword($this->investor(topUpCents: 5_000_00));
        $campaign = $this->liveCampaign(targetMvr: 5_000);
        app(PledgeService::class)->pledge($investor, $campaign, 5_000_00);
        $this->assertSame(Campaign::STATUS_FUNDED, $campaign->fresh()->status);

        $this->openWhileSignedOut("/investor/campaigns/{$campaign->id}");
        $this->signIn($investor)->assertRedirect("/investor/campaigns/{$campaign->id}");
    }

    // ----- Signing in as someone who can't open the intended page -----

    public function test_a_borrower_signing_in_after_an_investors_intended_url_lands_on_the_borrower_portal(): void
    {
        $this->openWhileSignedOut('/investor/wallet');

        $this->signIn($this->user(Role::BORROWER))->assertRedirect('/borrower');
        $this->assertNull(session('url.intended'));
        $this->get('/borrower')->assertOk();
    }

    public function test_another_borrowers_request_goes_to_the_portal_not_a_404(): void
    {
        $campaign = Campaign::factory()->create();
        $this->openWhileSignedOut("/borrower/campaigns/{$campaign->id}");

        $this->signIn($this->user(Role::BORROWER))->assertRedirect('/borrower');
    }

    public function test_a_funded_campaign_the_investor_did_not_pledge_to_goes_to_the_portal_not_a_404(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 5_000);
        app(PledgeService::class)->pledge($this->investor(topUpCents: 5_000_00), $campaign, 5_000_00);
        $this->openWhileSignedOut("/investor/campaigns/{$campaign->id}");

        $this->signIn($this->withPassword($this->investor()))->assertRedirect('/investor');
    }

    public function test_a_conventional_campaign_for_a_shariah_only_investor_goes_to_the_portal(): void
    {
        $campaign = $this->liveCampaign(contract: 'conventional');
        $this->openWhileSignedOut("/investor/campaigns/{$campaign->id}");

        $this->signIn($this->withPassword($this->investor(shariahOnly: true)))->assertRedirect('/investor');
    }

    public function test_an_admin_page_goes_to_the_portal_for_an_investor(): void
    {
        $applicant = $this->user(Role::BORROWER, verified: false);
        $this->openWhileSignedOut("/admin/verifications/{$applicant->id}");

        $this->signIn($this->withPassword($this->investor()))->assertRedirect('/investor');
    }

    public function test_an_address_that_no_longer_exists_goes_to_the_portal(): void
    {
        $this->openWhileSignedOut('/borrower/campaigns/999999');
        $this->signIn($this->user(Role::BORROWER))->assertRedirect('/borrower');
        $this->post('/logout');

        // A step the wizard doesn't have (the route itself refuses it, so it is set directly).
        session(['url.intended' => url('/borrower/onboarding/no-such-step')]);
        $this->signIn($this->user(Role::BORROWER))->assertRedirect('/borrower');
    }

    public function test_a_guest_page_or_another_site_is_never_returned_to(): void
    {
        $borrower = $this->user(Role::BORROWER);

        foreach (['/login', '/register', 'https://evil.example.com/borrower', '/up', '/no-such-page'] as $url) {
            session(['url.intended' => str_starts_with($url, 'http') ? $url : url($url)]);
            $this->signIn($borrower)->assertRedirect('/borrower');
            $this->post('/logout');
        }
    }

    public function test_a_document_goes_to_its_owner_as_a_full_page_load_and_never_to_anyone_else(): void
    {
        Storage::fake(KycDocumentStore::DISK);
        $document = KycDocument::factory()->create();
        Storage::disk(KycDocumentStore::DISK)->put($document->storage_path, '%PDF-1.4');
        $owner = $this->withPassword($document->user);

        // An Inertia visit can't show a PDF, so Inertia is told to load it as a full page.
        $this->openWhileSignedOut("/documents/{$document->id}");
        $this->withHeader('X-Inertia', 'true')->signIn($owner)
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url("/documents/{$document->id}"));
        $this->flushHeaders();
        $this->post('/logout');

        $this->openWhileSignedOut("/documents/{$document->id}");
        $this->signIn($this->user(Role::BORROWER))->assertRedirect('/borrower');
    }

    // ----- Signing out, and sessions that expired -----

    public function test_signing_out_clears_the_intended_url(): void
    {
        $investor = $this->withPassword($this->investor());
        $this->actingAs($investor)->withSession(['url.intended' => url('/investor/wallet')]);

        $this->post('/logout')->assertRedirect('/');
        $this->assertNull(session('url.intended'));
        $this->signIn($this->user(Role::BORROWER))->assertRedirect('/borrower');
    }

    public function test_signing_out_after_the_session_expired_leaves_no_intended_url_behind(): void
    {
        $campaign = Campaign::factory()->create();

        // The session is gone, but the page is still open and its Sign out is clicked.
        $this->from("/borrower/campaigns/{$campaign->id}")->post('/logout')->assertRedirect('/');
        $this->assertNull(session('url.intended'), 'Laravel would have remembered the page the form came from.');

        $this->signIn($this->user(Role::BORROWER))->assertRedirect('/borrower');
    }

    public function test_any_other_form_sent_after_the_session_expired_goes_to_sign_in_with_nothing_remembered(): void
    {
        $this->from('/investor/wallet')->post('/investor/wallet/top-up', ['amount' => 100])->assertRedirect('/login');
        $this->assertNull(session('url.intended'));

        $this->signIn($this->user(Role::BORROWER))->assertRedirect('/borrower');
    }

    public function test_a_page_opened_after_the_session_expired_is_returned_to_by_the_same_user_only(): void
    {
        // The admin queue's 30-second poll after the session expired: a GET, remembered.
        $this->openWhileSignedOut('/admin/verifications');
        $this->signIn($this->user(Role::ADMIN))->assertRedirect('/admin/verifications');
        $this->post('/logout');

        $this->openWhileSignedOut('/admin/verifications');
        $this->signIn($this->withPassword($this->investor()))->assertRedirect('/investor');
    }

    public function test_an_unused_intended_url_is_not_carried_to_a_later_sign_in(): void
    {
        $this->openWhileSignedOut('/investor/wallet');
        $this->signIn($this->user(Role::BORROWER))->assertRedirect('/borrower'); // not theirs: dropped
        $this->post('/logout');

        $this->signIn($this->withPassword($this->investor()))->assertRedirect('/investor');
    }

    // ----- Registration and password reset follow the same rule -----

    public function test_registering_goes_to_the_intended_page_only_if_the_new_account_may_open_it(): void
    {
        $this->openWhileSignedOut('/investor/campaigns');
        $this->post('/register', ['name' => 'Aisha Ibrahim', 'email' => 'aisha@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'role' => Role::INVESTOR])
            ->assertRedirect('/investor/campaigns')->assertSessionHas('status', 'Your account has been created.');
        $this->post('/logout');

        $this->openWhileSignedOut('/investor/campaigns');
        $this->post('/register', ['name' => 'Hassan Ali', 'email' => 'hassan@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'role' => Role::BORROWER])
            ->assertRedirect('/borrower')->assertSessionHas('status', 'Your account has been created.');
        $this->assertNull(session('url.intended'));
    }

    public function test_after_a_password_reset_the_sign_in_follows_the_same_rule(): void
    {
        $borrower = $this->user(Role::BORROWER);
        $campaign = Campaign::factory()->create(); // someone else's request
        $this->openWhileSignedOut("/borrower/campaigns/{$campaign->id}");

        $this->post('/reset-password', ['token' => Password::createToken($borrower), 'email' => $borrower->email, 'password' => 'New#pass2026', 'password_confirmation' => 'New#pass2026'])
            ->assertRedirect('/login');
        $this->post('/login', ['email' => $borrower->email, 'password' => 'New#pass2026'])->assertRedirect('/borrower');
        $this->post('/logout');

        $own = Campaign::factory()->for($borrower, 'borrower')->create();
        $this->openWhileSignedOut("/borrower/campaigns/{$own->id}");
        $this->post('/reset-password', ['token' => Password::createToken($borrower), 'email' => $borrower->email, 'password' => 'Newer#pass2026', 'password_confirmation' => 'Newer#pass2026']);
        $this->post('/login', ['email' => $borrower->email, 'password' => 'Newer#pass2026'])->assertRedirect("/borrower/campaigns/{$own->id}");
    }

    // ----- Every route a user could be sent back to is covered -----

    public function test_every_signed_in_page_with_a_parameter_has_an_access_check(): void
    {
        $missing = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('GET', $route->methods(), true)
                && in_array('auth', $route->gatherMiddleware(), true)
                && $route->parameterNames() !== [])
            ->map(fn (Route $route) => $route->getName() ?? $route->uri())
            ->reject(fn (string $name) => array_key_exists($name, IntendedUrl::checks()))
            ->values()->all();

        $this->assertSame([], $missing, 'Add these to IntendedUrl::checks(), or a user could be sent to one that 404s for them.');
    }
}
