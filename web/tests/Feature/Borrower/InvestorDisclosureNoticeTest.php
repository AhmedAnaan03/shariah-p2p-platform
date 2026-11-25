<?php

namespace Tests\Feature\Borrower;

use App\Models\BorrowerProfile;
use App\Models\User;
use App\Support\InvestorDisclosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-17: before investors see a borrower's credit history answers and repayment history on the
 * campaign page, the borrower is told so, on the onboarding finance step and the financing
 * request form; an individual is also told their name is never shown.
 */
class InvestorDisclosureNoticeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function pages(): array
    {
        return [
            'onboarding finance step' => ['/borrower/onboarding/finances'],
            'financing request form' => ['/borrower/campaigns/create'],
        ];
    }

    private static function borrower(bool $sme): User
    {
        $user = User::factory()->borrower()->verified()->create();
        ($sme ? BorrowerProfile::factory() : BorrowerProfile::factory()->individual())->for($user)->create();

        return $user;
    }

    #[DataProvider('pages')]
    public function test_an_individual_borrower_is_told_what_investors_see_and_that_their_name_is_never_shown(string $url): void
    {
        $this->actingAs(self::borrower(false))->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('investorDisclosure.title', InvestorDisclosure::TITLE)
                ->where('investorDisclosure.body.0', fn (string $text) => str_contains($text, 'credit history answers') && str_contains($text, 'repayment history'))
                ->where('investorDisclosure.body.1', fn (string $text) => str_contains($text, 'never see your name')));
    }

    #[DataProvider('pages')]
    public function test_an_sme_borrower_is_told_their_business_name_is_shown(string $url): void
    {
        $this->actingAs(self::borrower(true))->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('investorDisclosure.body.0', fn (string $text) => str_contains($text, 'credit history answers') && str_contains($text, 'repayment history'))
                ->where('investorDisclosure.body.1', fn (string $text) => str_contains($text, 'business name')));
    }

    public function test_both_pages_render_the_notice_component(): void
    {
        // The pages put the prop on screen through DisclosureNotice.
        foreach (['Onboarding/Finances.jsx', 'Borrower/Campaigns/Create.jsx'] as $page) {
            $source = file_get_contents(resource_path("js/pages/{$page}"));
            $this->assertStringContainsString('<DisclosureNotice notice={investorDisclosure} />', $source, $page);
        }
    }
}
