<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PB-51: the design preview page exists on a developer's machine only (APP_ENV=local). It must
 * never be registered in tests or production.
 */
class DesignPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_design_preview_is_not_registered_outside_local(): void
    {
        $this->assertFalse(Route::has('dev.design'));

        $this->get('/dev/design')->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get('/dev/design')->assertNotFound();
    }
}
