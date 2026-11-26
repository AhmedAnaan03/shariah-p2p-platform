<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use App\Services\KycDocumentStore;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Where to send a user who has just signed in or registered: the page they were trying to open
 * (Laravel's "intended" URL), but only if it is theirs to open, otherwise their portal home.
 *
 * The intended URL is stored by the 'auth' middleware when a signed-out browser asks for a
 * protected page. It belongs to the browser, not to a person: after a session expires, the next
 * click stores the page that was open, and whoever signs in next in that browser would be sent
 * there, to someone else's request (404) or another role's page (403). Inertia then shows that
 * error in a dialog over the sign-in page. So the URL is checked here, for this user:
 *
 * 1. it is a page of this app that needs signing in (not a guest page or another site);
 * 2. every `role:` middleware on its route matches the user's role;
 * 3. its route parameters resolve (the campaign, document or user exists);
 * 4. a route with a parameter passes the same check its controller makes (CHECKS below). A
 *    parameterised route with no entry is never returned to, so a new one can't slip through
 *    (IntendedUrlTest checks every such route has an entry).
 *
 * The URL is always removed from the session, used or not, so it can't apply to a later sign-in.
 */
final class IntendedUrl
{
    /** Routes that answer with a file rather than an Inertia page: a full page load, not an Inertia visit. */
    private const FILES = ['documents.show'];

    /**
     * Where to go after signing in or registering, as a response.
     */
    public static function redirect(Request $request, User $user): Response
    {
        $url = self::pullFor($request, $user);

        if ($url === null) {
            return redirect()->route($user->portalRoute());
        }

        return self::isFile($url) ? Inertia::location($url) : redirect()->to($url);
    }

    /**
     * The intended URL if $user may open it, else null. Removes it from the session either way.
     */
    public static function pullFor(Request $request, User $user): ?string
    {
        $url = $request->session()->pull('url.intended');

        return is_string($url) && self::allows($user, $url) ? $url : null;
    }

    public static function allows(User $user, string $url): bool
    {
        $route = self::route($url);
        if ($route === null) {
            return false;
        }

        $middleware = $route->gatherMiddleware();
        if (! in_array('auth', $middleware, true)) {
            return false; // a guest page (sign-in itself) or a public one: the portal is the place
        }
        foreach ($middleware as $name) {
            if (is_string($name) && str_starts_with($name, 'role:') && ! $user->hasRole(substr($name, 5))) {
                return false;
            }
        }

        if ($route->parameterNames() === [] && ! self::hasCanMiddleware($middleware)) {
            return true; // the role decides who may open it
        }

        $check = self::checks()[$route->getName()] ?? null;

        return $check !== null && $check($user, $route);
    }

    /**
     * The route of $url with its parameters bound (models loaded), or null if it isn't a GET page
     * of this app or a parameter doesn't resolve.
     */
    private static function route(string $url): ?Route
    {
        $request = Request::create($url, 'GET');
        if ($request->getSchemeAndHttpHost() !== request()->getSchemeAndHttpHost()) {
            return null;
        }

        /** @var Router $router */
        $router = app('router');
        try {
            $route = $router->getRoutes()->match($request);
            $route->bind($request);
            $router->substituteBindings($route);
            $router->substituteImplicitBindings($route);
        } catch (HttpException|ModelNotFoundException|BackedEnumCaseNotFoundException) {
            return null; // no such page, wrong method, or the model doesn't exist
        }

        return $route;
    }

    /**
     * For each route with a parameter: whether $user would get the page rather than a 404 or 403.
     * These mirror the checks in the controllers and policies.
     *
     * @return array<string, Closure(User, Route): bool>
     */
    public static function checks(): array
    {
        return [
            // CampaignPolicy::browse: open campaigns, and any campaign the investor pledged to.
            // A conventional one hidden by Shariah-only is refused too (it redirects away).
            'investor.campaigns.show' => fn (User $user, Route $route) => Gate::forUser($user)->inspect('browse', $route->parameter('campaign'))->allowed(),
            // CampaignPolicy::view: a borrower's own requests only.
            'borrower.campaigns.show' => fn (User $user, Route $route) => Gate::forUser($user)->allows('view', $route->parameter('campaign')),
            // PB-29: the business's own Mudarabah (the form redirects if no period can be reported).
            'borrower.campaigns.reports.create' => fn (User $user, Route $route) => Gate::forUser($user)->allows('view', $route->parameter('campaign')),
            // role:admin is all it needs.
            'admin.campaigns.show' => fn () => true,
            'admin.reports.show' => fn () => true,
            // VerificationController::show: administrators are never in the queue.
            'admin.verifications.show' => fn (User $user, Route $route) => ! $route->parameter('user')->hasRole(Role::ADMIN),
            // OnboardingController::show: a step of this user's wizard (a later one redirects).
            'borrower.onboarding.show' => fn (User $user, Route $route) => OnboardingSteps::for($user)->has($route->parameter('step')),
            'investor.onboarding.show' => fn (User $user, Route $route) => OnboardingSteps::for($user)->has($route->parameter('step')),
            // KycDocumentPolicy::view (the owner or an administrator), and the file is still there.
            'documents.show' => fn (User $user, Route $route) => Gate::forUser($user)->allows('view', $route->parameter('document'))
                && app(KycDocumentStore::class)->exists($route->parameter('document')),
        ];
    }

    private static function isFile(string $url): bool
    {
        return in_array(self::route($url)?->getName(), self::FILES, true);
    }

    /**
     * @param  array<int, mixed>  $middleware
     */
    private static function hasCanMiddleware(array $middleware): bool
    {
        foreach ($middleware as $name) {
            if (is_string($name) && str_starts_with($name, 'can:')) {
                return true;
            }
        }

        return false;
    }
}
