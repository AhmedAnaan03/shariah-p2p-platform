<!DOCTYPE html>
{{-- data-mode / data-theme pick the design-system palette (resources/css/tailwind-theme.css): all
     four combinations of conventional / Shariah and light / dark.
     data-mode comes from the same shared prop React reads (App\Support\PlatformMode), and so does
     the theme (PB-55): a user who chose light or dark gets it written here by the server, so the
     first paint is already right. For "system" the server can't know the device's setting, so it
     writes data-theme-preference="system" and the small script below sets data-theme from the
     device before anything is painted (it runs while <head> is parsed, before the CSS applies). --}}
@php
    $theme = $page['props']['theme'] ?? 'system';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-mode="{{ $page['props']['mode'] ?? 'conventional' }}" data-theme="{{ $theme === 'dark' ? 'dark' : 'light' }}" data-theme-preference="{{ $theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                var html = document.documentElement;
                if (html.dataset.themePreference === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    html.dataset.theme = 'dark';
                }
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
