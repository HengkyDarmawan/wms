@extends('layouts.auth')

@section('title', __('Terima Undangan'))

@section('content')
    <h1 class="h2 mb-1">{{ __('Atur password Anda') }}</h1>
    <p class="text-muted mb-4">
        {{ __('Halo :nama, lengkapi data di bawah untuk mulai memakai akun Anda.', ['nama' => $user->name]) }}
    </p>

    <form method="POST" action="{{ route('invitation.store', $token) }}" novalidate>
        @csrf

        <div class="mb-3">
            <label class="form-label" for="email_display">{{ __('Email') }}</label>
            <input class="form-control" type="email" id="email_display" value="{{ $user->email }}" disabled>
        </div>

        <div class="mb-3">
            <label class="form-label" for="name">{{ __('Nama') }} <span class="wajib">*</span></label>
            <input class="form-control @error('name') is-invalid @enderror"
                   type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="100">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="phone">{{ __('Nomor WhatsApp') }}</label>
            <input class="form-control @error('phone') is-invalid @enderror"
                   type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}"
                   placeholder="+62812…" maxlength="20">
            <div class="form-text">{{ __('Opsional. Dipakai untuk notifikasi approval di fase berikutnya.') }}</div>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">{{ __('Password') }} <span class="wajib">*</span></label>
            <input class="form-control @error('password') is-invalid @enderror"
                   type="password" id="password" name="password" required autocomplete="new-password">
            <div class="form-text">{{ __('Minimal :n karakter.', ['n' => config('access.password.min_length')]) }}</div>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-4">
            <label class="form-label" for="password_confirmation">{{ __('Ulangi password') }} <span class="wajib">*</span></label>
            <input class="form-control" type="password" id="password_confirmation"
                   name="password_confirmation" required autocomplete="new-password">
        </div>

        <button class="btn btn-primary w-100 btn-lg" type="submit">{{ __('Simpan dan masuk') }}</button>
    </form>
@endsection
