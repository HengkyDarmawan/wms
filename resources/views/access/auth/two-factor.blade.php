@extends('layouts.auth')

@section('title', __('Verifikasi Dua Langkah'))

@section('content')
    <h1 class="h2 mb-1">{{ __('Verifikasi dua langkah') }}</h1>
    <p class="text-muted mb-4">{{ __('Masukkan kode 6 digit dari aplikasi authenticator Anda, atau salah satu kode pemulihan.') }}</p>

    <form method="POST" action="{{ route('two-factor.store') }}" novalidate>
        @csrf

        <div class="mb-4">
            <label class="form-label" for="code">{{ __('Kode') }} <span class="wajib">*</span></label>
            <input class="form-control form-control-lg text-center @error('code') is-invalid @enderror"
                   type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                   maxlength="40" required autofocus>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button class="btn btn-primary w-100 btn-lg" type="submit">{{ __('Verifikasi') }}</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center mt-4">
        @csrf
        <button class="btn btn-link nx-link small p-0" type="submit">{{ __('Batal dan kembali ke halaman masuk') }}</button>
    </form>
@endsection
