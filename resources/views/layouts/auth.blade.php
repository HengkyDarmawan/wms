<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Masuk')) &middot; {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.svg') }}">
    {{-- PWA installable (Blueprint §11, A-193). --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2563eb">
    <link rel="apple-touch-icon" href="/img/icons/icon-192.png">
    {{-- Tema dipasang sebelum paint agar tidak berkedip (NexaDash theme-init). --}}
    <script>
        (function () {
            var t = localStorage.getItem('nx-theme');
            if (!t) { t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-nx-root="{{ url('/') }}">

<div class="nx-auth">

    <aside class="nx-auth-brand">
        <a class="nx-brand-row text-white text-decoration-none" href="{{ url('/') }}">
            <img src="{{ asset('img/logo.svg') }}" alt="" width="32" height="32">
            <span>{{ config('app.name') }}</span>
        </a>

        <div>
            <h2>{{ __('Stok yang bisa dipercaya, dari gudang sampai site.') }}</h2>

            <div class="nx-auth-feature">
                <i class="bi bi-diagram-3"></i>
                <div>
                    <div class="nx-af-title">{{ __('Gudang mengikuti proyek') }}</div>
                    <div class="nx-af-text">{{ __('Gudang utama, cabang, sampai titik site; stok terlihat per proyek.') }}</div>
                </div>
            </div>

            <div class="nx-auth-feature">
                <i class="bi bi-scissors"></i>
                <div>
                    <div class="nx-af-title">{{ __('Konversi material tercatat') }}</div>
                    <div class="nx-af-text">{{ __('Potongan, sisa, dan waste punya silsilah sendiri.') }}</div>
                </div>
            </div>

            <div class="nx-auth-feature">
                <i class="bi bi-shield-check"></i>
                <div>
                    <div class="nx-af-title">{{ __('Setiap mutasi punya jejak') }}</div>
                    <div class="nx-af-text">{{ __('Kartu stok permanen, timeline dokumen, dan jejak audit.') }}</div>
                </div>
            </div>
        </div>

        <div class="nx-auth-testimonial">
            <p class="mb-0">{{ __('Satu sistem untuk permintaan, pengiriman, retur, dan opname — tanpa menghitung ulang di Excel.') }}</p>
        </div>
    </aside>

    <main class="nx-auth-form-wrap">
        <div class="nx-auth-card">
            <div class="d-lg-none d-flex align-items-center gap-2 mb-4">
                <img src="{{ asset('img/logo.svg') }}" alt="" width="32" height="32">
                <span class="h4 mb-0">{{ config('app.name') }}</span>
            </div>

            @if (tenant())
                <div class="text-muted small mb-2">
                    <i class="bi bi-building"></i> {{ tenant()->name }}
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert-success py-2 small" role="status">{{ session('status') }}</div>
            @endif

            @yield('content')
        </div>
    </main>
</div>

</body>
</html>
