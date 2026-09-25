<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Super Admin')) &middot; {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.svg') }}">
    <script>
        (function () {
            var t = localStorage.getItem('nx-theme');
            if (!t) { t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-nx-root="{{ url('/') }}">
<a class="visually-hidden-focusable" href="#konten">{{ __('Lewati ke konten') }}</a>

<nav class="navbar navbar-expand-md border-bottom bg-body">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('platform.dashboard') }}">
            <img src="{{ asset('img/logo.svg') }}" alt="" width="28" height="28">
            <span>{{ __('Super Admin') }}</span>
        </a>
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('platform.dashboard', 'platform.companies.*') ? 'active' : '' }}" href="{{ route('platform.dashboard') }}">{{ __('Company') }}</a></li>
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('platform.payments.*') ? 'active' : '' }}" href="{{ route('platform.payments.index') }}">{{ __('Pembayaran') }}</a></li>
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('platform.plans.*') ? 'active' : '' }}" href="{{ route('platform.plans.index') }}">{{ __('Paket') }}</a></li>
        </ul>
        <a class="small me-3 {{ request()->routeIs('platform.security') ? 'fw-semibold' : 'text-muted' }}" href="{{ route('platform.security') }}"
           title="{{ __('Keamanan akun') }}">
            {{ auth('platform')->user()?->name }}
            @unless (auth('platform')->user()?->hasTwoFactorEnabled())
                <span class="badge text-bg-warning ms-1">{{ __('2FA mati') }}</span>
            @endunless
        </a>
        <form method="POST" action="{{ route('platform.logout') }}">
            @csrf
            <button class="btn btn-outline-secondary btn-sm" type="submit">{{ __('Keluar') }}</button>
        </form>
    </div>
</nav>

<main class="container-fluid py-4" id="konten">
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    {{-- Keamanan akun menampilkan galatnya sendiri per bagian (partial 2FA). --}}
    @if ($errors->any() && ! request()->routeIs('platform.security'))
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
            @if (session('ruleCode')) <span class="badge text-bg-dark ms-1">{{ session('ruleCode') }}</span> @endif
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>
