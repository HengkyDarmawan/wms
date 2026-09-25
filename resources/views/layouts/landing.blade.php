<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('description')">
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="icon" href="{{ asset('img/logo.svg') }}">
    <meta name="theme-color" content="#0c2a27">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="@yield('title', config('app.name'))">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:url" content="{{ url('/') }}">
    {{-- Landing tanpa manifest & service worker PWA: PWA hanya di subdomain company (A-193). --}}
    {{-- Tema dipasang sebelum paint, kunci sama dengan aplikasi (nx-theme). --}}
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('nx-theme'); } catch (e) {}
            if (t !== 'dark' && t !== 'light') { t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    @fonts
    @vite(['resources/css/landing.css', 'resources/js/landing.js'])
</head>
<body class="lp">
<a class="lp-skip" href="#konten">{{ __('Langsung ke konten') }}</a>

@yield('body')

</body>
</html>
