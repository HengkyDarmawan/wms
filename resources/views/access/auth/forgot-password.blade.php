@extends('layouts.auth')

@section('title', __('Lupa Password'))

@section('content')
    <h1 class="h2 mb-1">{{ __('Lupa password') }}</h1>
    <p class="text-muted mb-4">{{ __('Masukkan email Anda. Bila terdaftar, kami kirim tautan untuk mengatur ulang password.') }}</p>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="mb-4">
            <label class="form-label" for="email">{{ __('Email') }} <span class="wajib">*</span></label>
            <input class="form-control @error('email') is-invalid @enderror"
                   type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button class="btn btn-primary w-100 btn-lg" type="submit">{{ __('Kirim tautan') }}</button>
    </form>

    <p class="text-center small mt-4 mb-0">
        <a class="nx-link" href="{{ route('login') }}">{{ __('Kembali ke halaman masuk') }}</a>
    </p>
@endsection
