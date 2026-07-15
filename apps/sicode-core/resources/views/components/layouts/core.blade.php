<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme-mode="system">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'SICODE CORE' }}</title>

        <script>
            (function () {
                const stored = localStorage.getItem('sicode-theme-mode') || 'system';
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const resolved = stored === 'system' ? (prefersDark ? 'dark' : 'light') : stored;
                document.documentElement.dataset.themeMode = stored;
                document.documentElement.dataset.theme = resolved;
                document.documentElement.style.colorScheme = resolved;
            })();
        </script>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-background font-sans text-body text-text antialiased">
        <a href="#conteudo-principal" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-2 focus:text-label focus:font-semibold focus:text-primary focus:shadow-sicode-md">
            Pular para o conteúdo principal
        </a>

        {{ $slot }}
    </body>
</html>
