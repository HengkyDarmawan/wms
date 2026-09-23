<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/logo.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="container py-5">
    <div class="text-center" style="max-width: 40rem; margin: 0 auto;">
        <img src="{{ asset('img/logo.svg') }}" alt="" width="56" height="56" class="mb-3">
        <h1 class="h2 mb-2">{{ config('app.name') }}</h1>
        <p class="text-muted">
            {{ __('Warehouse Management System untuk perusahaan penyedia material dan alat proyek.') }}
        </p>
        <p class="text-muted small mb-0">
            {{ __('Setiap company masuk lewat subdomainnya sendiri, misalnya demo.:domain.', [
                'domain' => config('tenancy.central_domains.0', 'wms.test'),
            ]) }}
        </p>
    </div>
</main>
</body>
</html>
