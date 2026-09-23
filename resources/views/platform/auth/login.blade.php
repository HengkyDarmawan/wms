@extends('layouts.auth')

@section('title', __('Masuk Super Admin'))

@section('content')
    <h1 class="h2 mb-1">{{ __('Super Admin') }}</h1>
    <p class="text-muted mb-4">
        {{ __('Masuk untuk mengelola company dan langganan. Data operasional company hanya bisa dibuka lewat akses dukungan berperiode.') }}
    </p>

    @error('email')
        <div class="alert alert-danger py-2 small" role="alert">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('platform.login.store') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label class="form-label" for="email">{{ __('Email') }} <span class="wajib">*</span></label>
            <input class="form-control @error('email') is-invalid @enderror"
                   type="email" id="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username">
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">{{ __('Password') }} <span class="wajib">*</span></label>
            <input class="form-control @error('password') is-invalid @enderror"
                   type="password" id="password" name="password" required autocomplete="current-password">
            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
            <label class="form-check-label" for="remember">{{ __('Ingat saya') }}</label>
        </div>

        <button class="btn btn-primary w-100" type="submit">{{ __('Masuk') }}</button>
    </form>
@endsection
