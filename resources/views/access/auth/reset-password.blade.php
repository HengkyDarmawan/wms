@extends('layouts.auth')

@section('title', __('Atur Ulang Password'))

@section('content')
    <h1 class="h2 mb-1">{{ __('Atur ulang password') }}</h1>
    <p class="text-muted mb-4">
        {{ __('Password minimal :n karakter dan tidak boleh sama dengan :h password terakhir.', [
            'n' => config('access.password.min_length'),
            'h' => config('access.password.history'),
        ]) }}
    </p>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label class="form-label" for="email">{{ __('Email') }} <span class="wajib">*</span></label>
            <input class="form-control @error('email') is-invalid @enderror"
                   type="email" id="email" name="email" value="{{ old('email', request('email')) }}" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">{{ __('Password baru') }} <span class="wajib">*</span></label>
            <input class="form-control @error('password') is-invalid @enderror"
                   type="password" id="password" name="password" required autocomplete="new-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-4">
            <label class="form-label" for="password_confirmation">{{ __('Ulangi password baru') }} <span class="wajib">*</span></label>
            <input class="form-control" type="password" id="password_confirmation"
                   name="password_confirmation" required autocomplete="new-password">
        </div>

        <button class="btn btn-primary w-100 btn-lg" type="submit">{{ __('Simpan password') }}</button>
    </form>
@endsection
