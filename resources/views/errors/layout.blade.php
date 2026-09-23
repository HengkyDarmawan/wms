<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') &middot; {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.svg') }}">
    @vite(['resources/css/app.css'])
</head>
<body>
{{-- NFR-01: halaman error bermerek, tanpa jejak teknis. --}}
<main class="container d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center" style="max-width:32rem">
        <img src="{{ asset('img/logo.svg') }}" alt="" width="48" height="48" class="mb-3">
        <div class="display-4 fw-bold mb-1">@yield('code')</div>
        <h1 class="h4 mb-2">@yield('title')</h1>
        <p class="text-muted mb-4">@yield('message')</p>
        <a class="btn btn-outline-secondary" href="{{ url('/') }}">{{ __('Kembali ke beranda') }}</a>
    </div>
</main>
</body>
</html>
