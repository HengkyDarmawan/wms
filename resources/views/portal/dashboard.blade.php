@extends('layouts.app')

@section('title', __('Portal Klien'))

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">{{ __('Portal Klien') }}</h1>
        <p class="text-muted mb-0">{{ $user->name }} &middot; {{ $company?->name }}</p>
    </div>

    <div class="card">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Proyek Anda') }}</h2>
            <p class="text-muted mb-0">
                {{ __('Daftar proyek, permintaan material, dan pengiriman muncul di sini setelah modul Master dan Request dibangun.') }}
            </p>
        </div>
    </div>
@endsection
