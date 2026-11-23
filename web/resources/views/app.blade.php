<!DOCTYPE html>
{{-- data-mode / data-theme pick the design-system palette (resources/css/tailwind-theme.css): all
     four combinations of conventional / Shariah and light / dark.
     data-mode comes from the same shared prop React reads (App\Support\PlatformMode), and so does
     the theme (PB-55, PB-57): the user's last choice (light, dark, or none) and the device's
     setting when they made it. The most recent change wins, and the small script below decides
     before anything is painted: no choice, or a device setting that has changed since the choice
     → follow the device; otherwise keep the choice. The server writes the choice (or light) so a
     page without JavaScript still has a theme. --}}
@php
    $theme = $page['props']['theme'] ?? ['choice' => null, 'systemAtChoice' => null];
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-mode="{{ $page['props']['mode'] ?? 'conventional' }}" data-theme="{{ $theme['choice'] ?? 'light' }}" data-theme-choice="{{ $theme['choice'] }}" data-theme-system-at-choice="{{ $theme['systemAtChoice'] }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                var d = document.documentElement.dataset;
                var device = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                d.theme = d.themeChoice && (!d.themeSystemAtChoice || d.themeSystemAtChoice === device) ? d.themeChoice : device;
            })();
        </script>

        <title inertia>{{ config('app.name') }}</title>

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
