<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicons / brand (files live in public/brand) -->
        <link rel="icon" type="image/svg+xml" href="/brand/favicon.svg">
        <link rel="icon" type="image/png" sizes="32x32" href="/brand/favicon-32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/brand/favicon-16.png">
        <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
        <link rel="manifest" href="/brand/site.webmanifest">
        <meta name="theme-color" content="#4F46E5">

        <!-- Apply theme before paint to avoid flash -->
        <script>
        (function () {
            @auth
            var t = '{{ auth()->user()->theme ?? 'system' }}';
            @else
            var t = localStorage.getItem('theme') || 'system';
            @endauth
            if (t === 'dark') {
                document.documentElement.dataset.theme = 'dark';
            } else if (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />

        <!-- Runtime config exposed to the SPA (keeps the built image host-independent) -->
        <script>
            window.__TASKLINE__ = {!! json_encode([
                'appName' => config('app.name'),
                'reverb' => [
                    'key'    => config('taskline.reverb.key'),
                    'host'   => config('taskline.reverb.host'),
                    'port'   => config('taskline.reverb.port'),
                    'scheme' => config('taskline.reverb.scheme'),
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
