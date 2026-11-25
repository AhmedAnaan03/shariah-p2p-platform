<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Notifications\CampaignDecided;
use App\Services\CampaignReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The platform is called "Amanah P2P" everywhere it is shown: the wordmark, page titles,
 * emails and notices. The server's name is config('app.name') (APP_NAME); the browser's is
 * resources/js/lib/brand.js. These check that they agree and that the old short name is gone.
 */
class PlatformNameTest extends TestCase
{
    use RefreshDatabase;

    private const NAME = 'Amanah P2P';

    public function test_the_server_and_the_browser_use_the_same_name(): void
    {
        $this->assertSame(self::NAME, config('app.name'));
        $this->assertStringContainsString('APP_NAME="'.self::NAME.'"', file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString("PLATFORM_NAME = '".self::NAME."'", file_get_contents(resource_path('js/lib/brand.js')));
    }

    public function test_the_first_paint_title_is_the_platform_name(): void
    {
        $this->get('/login')->assertOk()->assertSee('<title inertia>'.self::NAME.'</title>', false);
    }

    public function test_emails_carry_the_platform_name(): void
    {
        $campaign = Campaign::factory()->murabahah()->live()->create();
        $html = (string) (new CampaignDecided($campaign, CampaignReviewService::APPROVE))->toMail($campaign->borrower)->render();

        $this->assertStringContainsString(self::NAME, $html);
    }

    public function test_the_old_short_name_is_not_shown_anywhere(): void
    {
        $files = [
            ...glob(resource_path('js/{components,pages,lib}/*.{js,jsx}'), GLOB_BRACE),
            ...glob(resource_path('js/pages/*/*.jsx')),
            ...glob(resource_path('js/pages/*/*/*.jsx')),
            ...glob(app_path('{Support,Notifications,Http/Controllers}/*.php'), GLOB_BRACE),
            resource_path('views/app.blade.php'),
        ];

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/Amanah(?! P2P)/', file_get_contents($file), $file);
        }
        $this->assertStringContainsString('{PLATFORM_NAME}', file_get_contents(resource_path('js/components/Wordmark.jsx')));
    }
}
