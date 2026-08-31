<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ asset('logo.jpg') }}" type="image/jpeg">

        <script>
            (function () {
                var appearances = ['light', 'dark', 'system'];
                var accents = ['calm', 'healing', 'alert', 'blood', 'seagrass'];
                var defaults = { appearance: 'system', accent: 'calm' };
                var accentMap = {
                    'calm-blue': 'calm',
                    'healing-green': 'healing',
                    'warm-gold': 'alert',
                    'vital-red': 'blood'
                };

                try {
                    var stored = JSON.parse(localStorage.getItem('public-theme-preference') || '{}');
                    var appearance = appearances.indexOf(stored.appearance) >= 0 ? stored.appearance : defaults.appearance;
                    var storedAccent = accentMap[stored.accent] || stored.accent;
                    var accent = accents.indexOf(storedAccent) >= 0 ? storedAccent : defaults.accent;
                    var resolved = appearance === 'system'
                        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                        : appearance;

                    document.documentElement.dataset.publicAppearance = resolved;
                    document.documentElement.dataset.publicAppearancePreference = appearance;
                    document.documentElement.dataset.publicAccent = accent;
                    document.documentElement.style.colorScheme = resolved;
                } catch (error) {
                    document.documentElement.dataset.publicAppearance = 'light';
                    document.documentElement.dataset.publicAppearancePreference = 'system';
                    document.documentElement.dataset.publicAccent = 'calm';
                }
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-inertia::head />
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
