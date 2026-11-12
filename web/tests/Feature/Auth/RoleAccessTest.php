<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-07: every route is gated by role.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every role against every portal: [role, portal URL, expected HTTP status].
     *
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function rolePortalMatrix(): array
    {
        return [
            'borrower opens borrower portal' => ['borrower', '/borrower', 200],
            'borrower opens investor portal' => ['borrower', '/investor', 403],
            'borrower opens admin portal' => ['borrower', '/admin', 403],
            'investor opens borrower portal' => ['investor', '/borrower', 403],
            'investor opens investor portal' => ['investor', '/investor', 200],
            'investor opens admin portal' => ['investor', '/admin', 403],
            'admin opens borrower portal' => ['admin', '/borrower', 403],
            'admin opens investor portal' => ['admin', '/investor', 403],
            'admin opens admin portal' => ['admin', '/admin', 200],
        ];
    }

    #[DataProvider('rolePortalMatrix')]
    public function test_each_role_can_open_only_its_own_portal(string $role, string $url, int $status): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)->get($url)->assertStatus($status);
    }

    public function test_a_borrower_cannot_open_investor_or_admin_routes(): void
    {
        // The acceptance criterion, stated on its own.
        $borrower = User::factory()->borrower()->create();

        $this->actingAs($borrower)->get('/investor')->assertForbidden();
        $this->actingAs($borrower)->get('/admin')->assertForbidden();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function signedInOnlyUrls(): array
    {
        return [
            'borrower portal' => ['/borrower'],
            'investor portal' => ['/investor'],
            'admin portal' => ['/admin'],
            'change password' => ['/account/password'],
        ];
    }

    #[DataProvider('signedInOnlyUrls')]
    public function test_guests_are_sent_to_sign_in(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    public function test_each_portal_renders_its_own_page(): void
    {
        foreach (['borrower' => 'Borrower/Portal', 'investor' => 'Investor/Portal', 'admin' => 'Admin/Portal'] as $role => $component) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)->get("/{$role}")->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('auth.user.role', $role)
            );
        }
    }

    public function test_the_shared_user_props_never_include_the_password_hash(): void
    {
        $user = User::factory()->borrower()->create();

        $this->actingAs($user)->get('/borrower')->assertInertia(fn (Assert $page) => $page
            ->has('auth.user', fn (Assert $props) => $props
                ->hasAll(['id', 'name', 'email', 'role', 'role_label', 'status', 'status_reason'])
                ->missing('password')
                ->missing('remember_token')
            )
        );
    }

    /**
     * Pages anyone may open without signing in. Every other application route must need
     * 'auth', and every portal route must need its role.
     */
    private const PUBLIC_URIS = ['/', 'register', 'login', 'forgot-password', 'reset-password/{token}', 'reset-password'];

    public function test_every_application_route_outside_the_public_list_requires_sign_in(): void
    {
        $checked = 0;

        foreach ($this->applicationRoutes() as $route) {
            if (in_array($route->uri(), self::PUBLIC_URIS, true)) {
                continue;
            }

            $this->assertContains('auth', $route->gatherMiddleware(), "Route [{$route->uri()}] is not behind 'auth'.");
            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }

    public function test_every_portal_route_requires_its_role(): void
    {
        foreach ($this->applicationRoutes() as $route) {
            foreach (['borrower', 'investor', 'admin'] as $role) {
                if ($route->uri() === $role || str_starts_with($route->uri(), "{$role}/")) {
                    $this->assertContains("role:{$role}", $route->gatherMiddleware(), "Route [{$route->uri()}] is not behind 'role:{$role}'.");
                }
            }
        }
    }

    /**
     * Routes handled by this app's controllers (not framework or package routes such as /up).
     *
     * @return array<int, Route>
     */
    private function applicationRoutes(): array
    {
        return array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $route) => str_starts_with($route->getActionName(), 'App\\Http\\Controllers\\'),
        );
    }
}
