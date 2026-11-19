<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Return navigation (PB-54): links between pages carry ?return_to=<the page they came from>,
 * and back links and breadcrumbs on the next page go there. For example: campaign review →
 * the borrower's verification record → "Back to the campaign review", with the campaign list's
 * filters still in that review's own return_to.
 *
 * The value comes from the URL, so it is checked before it is ever used as a link or redirect
 * (an "open redirect" would let a crafted link send a signed-in admin to another site). Only an
 * internal path is accepted: it must start with a single "/", contain no backslash, scheme,
 * host, spaces or control characters, and match one of this app's GET routes. Anything else
 * falls back to the page's normal parent.
 */
final class ReturnUrl
{
    public const PARAM = 'return_to';

    public const MAX_LENGTH = 2000;

    /**
     * Route name => [back link text, breadcrumb text]. A valid path to another page gets a plain
     * "Back".
     */
    private const LABELS = [
        'admin.portal' => ['Back to overview', 'Overview'],
        'admin.verifications.index' => ['Back to the verification queue', 'Verification queue'],
        'admin.verifications.show' => ['Back to the verification record', 'Verification record'],
        'admin.campaigns.index' => ['Back to campaigns', 'Campaigns'],
        'admin.campaigns.show' => ['Back to the campaign review', 'Campaign review'],
        'borrower.portal' => ['Back to overview', 'Overview'],
        'borrower.campaigns.index' => ['Back to your requests', 'Your requests'],
        'investor.portal' => ['Back to overview', 'Overview'],
        'investor.campaigns.index' => ['Back to the marketplace', 'Marketplace'],
    ];

    /**
     * The internal path (with its query string) if $value is safe to link or redirect to,
     * otherwise null.
     */
    public static function sanitize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        // One leading slash, never two ("//evil.example" is a link to another site), and no
        // backslash (browsers treat "/\evil.example" like "//evil.example").
        if ($value[0] !== '/' || str_starts_with($value, '//') || str_contains($value, '\\')) {
            return null;
        }

        // No spaces or control characters (header injection, " /..." tricks), in the raw value
        // or once percent-decoded.
        $decoded = rawurldecode($value);
        if (preg_match('/[\x00-\x20\x7F]/', $value) || preg_match('/[\x00-\x1F\x7F]/', $decoded)
            || str_starts_with($decoded, '//') || str_contains($decoded, '\\')) {
            return null;
        }

        $parts = parse_url($value);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || ! isset($parts['path'])) {
            return null;
        }

        // It must be a page of this app.
        if (self::routeFor($parts['path']) === null) {
            return null;
        }

        return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * Where the page's back link and breadcrumb go: the request's return_to when it is safe,
     * otherwise the fallback (the page's normal parent).
     *
     * @return array{url: string, label: string, crumb: string, fromLink: bool}
     */
    public static function back(Request $request, string $fallbackRoute, array $fallbackParameters = []): array
    {
        $path = self::sanitize($request->query(self::PARAM));

        if ($path === null) {
            [$label, $crumb] = self::LABELS[$fallbackRoute] ?? ['Back', 'Back'];

            return ['url' => route($fallbackRoute, $fallbackParameters, false), 'label' => $label, 'crumb' => $crumb, 'fromLink' => false];
        }

        [$label, $crumb] = self::LABELS[self::routeFor(parse_url($path, PHP_URL_PATH))] ?? ['Back', 'Previous page'];

        return ['url' => $path, 'label' => $label, 'crumb' => $crumb, 'fromLink' => true];
    }

    /**
     * The name of the GET route a path belongs to, or null if it is not one of the app's pages.
     */
    private static function routeFor(string $path): ?string
    {
        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (Throwable) {
            return null;
        }

        return $route->getName() ?? '';
    }
}
