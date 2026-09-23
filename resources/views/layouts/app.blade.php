<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Beranda')) &middot; {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.svg') }}">
    <script>
        (function () {
            var t = localStorage.getItem('nx-theme');
            if (!t) { t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    @include('layouts.partials.palette')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-nx-root="{{ url('/') }}">

@include('layouts.partials.sidebar')

<div class="nx-main">
    @include('layouts.partials.header')

    <main class="nx-content">
        @include('layouts.partials.subscription-banner')

        @if (session('status'))
            <div class="alert alert-success py-2" role="status">{{ session('status') }}</div>
        @endif

        {{-- Pesan singkat dari komponen Livewire (event `pesan`). --}}
        <div x-data="{ tampil: false, teks: '', jenis: 'success' }"
             x-on:pesan.window="teks = $event.detail.teks; jenis = $event.detail.jenis || 'success'; tampil = true; setTimeout(() => tampil = false, 5000)"
             x-show="tampil" x-cloak class="alert py-2" x-bind:class="'alert-' + jenis" role="status">
            <span x-text="teks"></span>
        </div>

        @yield('content')
    </main>

    <footer class="nx-footer">
        <div>&copy; {{ date('Y') }} {{ config('app.name') }}</div>
        <div class="d-flex gap-3">
            <span class="text-muted small">{{ tenant()?->name }}</span>
        </div>
    </footer>
</div>

</body>
</html>
