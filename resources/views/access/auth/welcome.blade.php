@extends('layouts.auth')

@section('title', __('Selamat datang'))

@section('content')
    {{-- A-417: halaman depan tamu — tautan Masuk yang jelas untuk mengajar & demo. --}}
    <h1 class="h2 mb-1">{{ tenant()?->name }}</h1>
    <p class="text-muted mb-4">{{ __('Sistem gudang: stok, permintaan material, pengiriman ke proyek, dan opname dalam satu tempat.') }}</p>

    <a class="btn btn-primary w-100 btn-lg" href="{{ route('login') }}" data-tombol-masuk>
        <i class="bi bi-box-arrow-in-right"></i> {{ __('Masuk') }}
    </a>

    @include('access.auth.partials.coba-demo')

    <p class="text-center text-muted small mt-4 mb-0">
        {{ __('Klien proyek?') }} <a class="nx-link" href="{{ route('portal.login') }}">{{ __('Masuk lewat portal klien') }}</a>
    </p>
@endsection
