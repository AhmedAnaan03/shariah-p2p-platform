<?php

namespace Tests\Feature\Lists;

use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Models\User;
use App\Support\ListFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesOnboardedUsers;
use Tests\TestCase;

/**
 * PB-54: server-side search and filters on every list (admin verification queue, admin
 * campaigns in both tabs, borrower requests, investor marketplace). Each list echoes its
 * filters back so the bar shows them, ignores values that aren't allowed, and matches the
 * search text literally.
 */
class ListFiltersTest extends TestCase
{
    use CreatesOnboardedUsers, RefreshDatabase;

    // ----- The helper itself -----

    /**
     * [query string, expected filters echoed back]
     *
     * @return array<string, array{0: array<string, mixed>, 1: array<string, string>}>
     */
    public static function rawFilters(): array
    {
        return [
            'nothing (blank)' => [[], []],
            'blank search' => [['search' => '   '], []],
            'search trimmed' => [['search' => '  van  '], ['search' => 'van']],
            'search over 100 characters is cut' => [['search' => str_repeat('a', 150)], ['search' => str_repeat('a', 100)]],
            'search sent as an array' => [['search' => ['x']], []],
            'allowed status' => [['status' => 'live'], ['status' => 'live']],
            'status not on the list' => [['status' => 'deleted'], []],
            'status in the wrong case' => [['status' => 'LIVE'], []],
            'filter the list does not have' => [['colour' => 'red'], []],
        ];
    }

    #[DataProvider('rawFilters')]
    public function test_only_allowed_values_are_kept(array $query, array $expected): void
    {
        $filters = ListFilters::from(Request::create('/x', 'GET', $query), ['status' => ['live', 'rejected']]);

        $this->assertSame($expected, $filters->toArray());
    }

    public function test_like_wildcards_are_escaped(): void
    {
        $this->assertSame('100\\%', ListFilters::escapeLike('100%'));
        $this->assertSame('a\\_b', ListFilters::escapeLike('a_b'));
        $this->assertSame('back\\\\slash', ListFilters::escapeLike('back\\slash'));
    }

    // ----- Admin verification queue -----

    public function test_the_queue_is_searched_by_name_or_email_and_filtered_by_role_and_borrower_type(): void
    {
        $admin = User::factory()->admin()->create();
        $this->onboardedBorrower(['name' => 'Aishath Individual', 'email' => 'aishath@example.com']);
        $this->onboardedBorrower(['name' => 'Ibrahim Business', 'email' => 'ibrahim@example.com'], sme: true);
        $this->onboardedInvestor(['name' => 'Aishath Investor', 'email' => 'investor@example.com']);

        $names = fn (string $query) => collect($this->actingAs($admin)->get("/admin/verifications{$query}")
            ->assertOk()->viewData('page')['props']['applicants'])->pluck('name')->sort()->values()->all();

        $this->assertSame(['Aishath Individual', 'Aishath Investor', 'Ibrahim Business'], $names(''));
        $this->assertSame(['Aishath Individual', 'Aishath Investor'], $names('?search=aishath'));
        $this->assertSame(['Ibrahim Business'], $names('?search=ibrahim@'));
        $this->assertSame(['Aishath Investor'], $names('?role=investor'));
        $this->assertSame(['Ibrahim Business'], $names('?type=sme'));
        $this->assertSame(['Aishath Individual'], $names('?role=borrower&search=aishath'));
        $this->assertSame([], $names('?search=nobody'));
        $this->assertSame(['Aishath Individual', 'Aishath Investor', 'Ibrahim Business'], $names('?role=superuser'), 'An unknown role is ignored.');

        $this->actingAs($admin)->get('/admin/verifications?role=investor&search=%20aishath%20')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters', ['search' => 'aishath', 'role' => 'investor'])
                ->has('filterOptions.role', 2)
                ->has('filterOptions.type', 2)
            );
    }

    // ----- Admin campaigns (both tabs) -----

    public function test_admin_campaigns_are_filtered_in_both_tabs_and_the_tab_is_kept(): void
    {
        $admin = User::factory()->admin()->create();
        $borrower = User::factory()->borrower()->verified()->create(['name' => 'Mohamed Faiz']);
        Campaign::factory()->murabahah()->submitted()->for($borrower, 'borrower')->create(['title' => 'Refrigerated van', 'purpose' => 'growth']);
        Campaign::factory()->conventional()->submitted()->for($borrower, 'borrower')->create(['title' => 'Cold room', 'purpose' => 'growth']);
        Campaign::factory()->mudarabah()->live(now()->addDays(10))->for($borrower, 'borrower')->create(['title' => 'Tutoring centre', 'purpose' => 'education']);
        Campaign::factory()->murabahah()->live(now()->addDays(5))->for($borrower, 'borrower')->create(['title' => 'Dive compressor', 'purpose' => 'growth']);

        $titles = function (string $query) use ($admin) {
            $props = $this->actingAs($admin)->get("/admin/campaigns{$query}")->assertOk()->viewData('page')['props'];

            return [collect($props['toReview'])->pluck('title')->all(), collect($props['live'])->pluck('title')->all()];
        };

        $this->assertSame([['Refrigerated van', 'Cold room'], ['Dive compressor', 'Tutoring centre']], $titles(''));
        $this->assertSame([['Refrigerated van'], ['Dive compressor']], $titles('?contract=murabahah'));
        $this->assertSame([[], ['Tutoring centre']], $titles('?purpose=education'));
        $this->assertSame([['Refrigerated van'], []], $titles('?search=van'));
        $this->assertSame([['Refrigerated van', 'Cold room'], ['Dive compressor', 'Tutoring centre']], $titles('?search=faiz'), 'Searches the borrower name too.');
        $this->assertSame([['Refrigerated van', 'Cold room'], ['Dive compressor', 'Tutoring centre']], $titles('?contract=interest'), 'An unknown contract is ignored.');

        $this->actingAs($admin)->get('/admin/campaigns?tab=live&contract=murabahah')
            ->assertInertia(fn (Assert $page) => $page->where('filters', ['tab' => 'live', 'contract' => 'murabahah']));
    }

    // ----- Borrower requests -----

    public function test_a_borrower_filters_only_their_own_requests(): void
    {
        $borrower = User::factory()->borrower()->verified()->create();
        BorrowerProfile::factory()->for($borrower)->create();
        Campaign::factory()->murabahah()->submitted()->for($borrower, 'borrower')->create(['title' => 'Delivery van']);
        Campaign::factory()->conventional()->live(now()->addDays(9))->for($borrower, 'borrower')->create(['title' => 'Shop fit-out']);
        Campaign::factory()->conventional()->submitted()->for($borrower, 'borrower')->create(['title' => 'Old boat', 'status' => 'rejected', 'status_reason' => 'Not enough detail given.']);
        Campaign::factory()->murabahah()->submitted()->create(['title' => 'Someone else’s van']);

        $titles = fn (string $query) => collect($this->actingAs($borrower)->get("/borrower/campaigns{$query}")
            ->assertOk()->viewData('page')['props']['campaigns'])->pluck('title')->sort()->values()->all();

        $this->assertSame(['Delivery van', 'Old boat', 'Shop fit-out'], $titles(''));
        $this->assertSame(['Delivery van'], $titles('?search=van'));
        $this->assertSame(['Shop fit-out'], $titles('?status=live'));
        $this->assertSame(['Old boat'], $titles('?status=rejected'));
        $this->assertSame(['Old boat', 'Shop fit-out'], $titles('?contract=conventional'));
        $this->assertSame([], $titles('?status=live&contract=murabahah'));
        $this->assertSame([], $titles('?search=%25'), 'A typed % is matched literally, not as a wildcard.');

        $this->actingAs($borrower)->get('/borrower/campaigns?search=nothing')
            ->assertInertia(fn (Assert $page) => $page->where('hasAny', true)->has('campaigns', 0)->has('filterOptions.status', 8)); // PB-34: + funded, not funded, repaying; PB-36, PB-29: + completed
    }

    // ----- Investor marketplace -----

    public function test_the_marketplace_is_searched_by_title_or_business_name_and_filtered(): void
    {
        $investor = User::factory()->investor()->create();
        $sme = User::factory()->borrower()->verified()->create(['name' => 'Private Person Name']);
        BorrowerProfile::factory()->for($sme)->create(['business_name' => 'Reef Dive Supplies']);
        $individual = User::factory()->borrower()->verified()->create(['name' => 'Yoosuf Rameez']);
        BorrowerProfile::factory()->individual()->for($individual)->create();

        Campaign::factory()->murabahah()->live(now()->addDays(5))->for($sme, 'borrower')->create(['title' => 'Dive compressor', 'purpose' => 'growth']);
        Campaign::factory()->mudarabah()->live(now()->addDays(8))->for($individual, 'borrower')->create(['title' => 'Guest house fit-out', 'purpose' => 'start_up']);
        Campaign::factory()->conventional()->submitted()->for($sme, 'borrower')->create(['title' => 'Not approved yet']);

        $titles = fn (string $query) => collect($this->actingAs($investor)->get("/investor/campaigns{$query}")
            ->assertOk()->viewData('page')['props']['campaigns']['data'])->pluck('title')->all();

        $this->assertSame(['Dive compressor', 'Guest house fit-out'], $titles(''));
        $this->assertSame(['Dive compressor'], $titles('?search=reef'), 'Business names are searched.');
        $this->assertSame([], $titles('?search=yoosuf'), 'An individual borrower’s name is never searched (or shown).');
        $this->assertSame(['Guest house fit-out'], $titles('?contract=mudarabah'));
        $this->assertSame(['Dive compressor'], $titles('?purpose=growth'));
        $this->assertSame([], $titles('?search=not approved'), 'Only live campaigns are listed.');
    }

    // ----- Role access with filters -----

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function listsAndOtherRoles(): array
    {
        return [
            'borrower on the admin queue' => ['borrower', '/admin/verifications?search=a'],
            'investor on admin campaigns' => ['investor', '/admin/campaigns?tab=live'],
            'investor on borrower requests' => ['investor', '/borrower/campaigns?status=live'],
            'admin on borrower requests' => ['admin', '/borrower/campaigns?status=live'],
            'borrower on the marketplace' => ['borrower', '/investor/campaigns?contract=murabahah'],
        ];
    }

    #[DataProvider('listsAndOtherRoles')]
    public function test_filters_do_not_change_who_can_open_a_list(string $role, string $url): void
    {
        $this->actingAs(User::factory()->{$role}()->create())->get($url)->assertForbidden();
    }
}
