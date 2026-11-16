<!DOCTYPE html>
{{-- data-mode / data-theme pick the design-system palette (resources/css/tailwind-theme.css).
     data-mode comes from the same shared prop React reads (App\Support\PlatformMode), so the
     first paint is already in the right palette with no flash. There is no dark toggle yet. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-mode="{{ $page['props']['mode'] ?? 'conventional' }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name') }}</title>

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
