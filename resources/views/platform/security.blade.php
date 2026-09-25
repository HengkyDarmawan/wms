@extends('layouts.platform')

@section('title', __('Keamanan akun'))

@section('content')
    <div class="mb-3">
        <h1 class="h3 mb-1">{{ __('Keamanan akun') }}</h1>
        <p class="text-muted mb-0">{{ __('Akun Super Admin memegang semua company. Nyalakan verifikasi dua langkah supaya password saja tidak cukup untuk masuk.') }}</p>
    </div>

    <div class="row">
        <div class="col-lg-7">
            @include('access.partials.two-factor', ['user' => $user, 'sisaKodePemulihan' => $sisaKodePemulihan, 'rute' => 'platform.two-factor'])
        </div>
    </div>
@endsection
