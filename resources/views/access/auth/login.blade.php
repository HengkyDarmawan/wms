@extends('layouts.auth')

@section('title', $portal ? __('Masuk Portal Klien') : __('Masuk'))

@section('content')
    <h1 class="h2 mb-1">{{ __('Selamat datang') }}</h1>
    <p class="text-muted mb-4">
        {{ $portal ? __('Masuk ke portal klien untuk melacak permintaan dan pengiriman.') : __('Masuk untuk melanjutkan ke gudang Anda.') }}
    </p>

    @error('email')
        <div class="alert alert-danger py-2 small" role="alert">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ $portal ? route('portal.login.store') : route('login.store') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label class="form-label" for="email">{{ __('Email') }} <span class="wajib">*</span></label>
            <input class="form-control @error('email') is-invalid @enderror"
                   type="email" id="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username">
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <label class="form-label" for="password">{{ __('Password') }} <span class="wajib">*</span></label>
                <a class="nx-link small" href="{{ route('password.request') }}">{{ __('Lupa password?') }}</a>
            </div>
            <div class="input-group">
                <input class="form-control @error('password') is-invalid @enderror"
                       type="password" id="password" name="password" required autocomplete="current-password">
                <button class="btn btn-outline-secondary btn-toggle-pw" type="button"
                        data-target="#password" aria-label="{{ __('Tampilkan password') }}">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="form-check form-switch mb-4">
            <input class="form-check-input" type="checkbox" role="switch" id="remember" name="remember" value="1">
            <label class="form-check-label" for="remember">{{ __('Ingat saya di perangkat ini') }}</label>
        </div>

        <button class="btn btn-primary w-100 btn-lg" type="submit">{{ __('Masuk') }}</button>
    </form>

    <p class="text-center text-muted small mt-4 mb-0">
        @if ($portal)
            {{ __('Karyawan company?') }} <a class="nx-link" href="{{ route('login') }}">{{ __('Masuk di sini') }}</a>
        @else
            {{ __('Klien proyek?') }} <a class="nx-link" href="{{ route('portal.login') }}">{{ __('Masuk lewat portal klien') }}</a>
        @endif
    </p>
@endsection
