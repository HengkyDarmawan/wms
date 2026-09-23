@extends('layouts.app')

@section('title', __('Profil'))

@section('content')
    <h1 class="h3 mb-4">{{ __('Profil') }}</h1>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Data diri') }}</h2>

                    <form method="POST" action="{{ route('profile.update') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label" for="name">{{ __('Nama') }} <span class="wajib">*</span></label>
                            <input class="form-control @error('name') is-invalid @enderror" type="text"
                                   id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="email">{{ __('Email') }}</label>
                            <input class="form-control" type="email" id="email" value="{{ $user->email }}" disabled>
                            <div class="form-text">{{ __('Email diubah oleh Admin Company.') }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="phone">{{ __('Nomor WhatsApp') }}</label>
                            <input class="form-control @error('phone') is-invalid @enderror" type="text"
                                   id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button class="btn btn-primary" type="submit">{{ __('Simpan') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Keamanan') }}</h2>

                    <form method="POST" action="{{ route('profile.password') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label" for="current_password">{{ __('Password lama') }} <span class="wajib">*</span></label>
                            <input class="form-control @error('current_password') is-invalid @enderror"
                                   type="password" id="current_password" name="current_password" required>
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">{{ __('Password baru') }} <span class="wajib">*</span></label>
                            <input class="form-control @error('password') is-invalid @enderror"
                                   type="password" id="password" name="password" required>
                            <div class="form-text">
                                {{ __('Minimal :n karakter, tidak boleh sama dengan :h password terakhir.', [
                                    'n' => config('access.password.min_length'),
                                    'h' => config('access.password.history'),
                                ]) }}
                            </div>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password_confirmation">{{ __('Ulangi password baru') }} <span class="wajib">*</span></label>
                            <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required>
                        </div>

                        <button class="btn btn-primary" type="submit">{{ __('Ganti password') }}</button>
                    </form>
                </div>
            </div>

            @include('access.partials.signature')

            @include('access.partials.two-factor')

            <div class="card">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Perangkat terdaftar') }}</h2>

                    @forelse ($devices as $device)
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <div>
                                <div class="fw-semibold">{{ $device->name ?? $device->device_uid }}</div>
                                <div class="small text-muted">{{ $device->platform }}</div>
                            </div>
                            <span class="small text-muted">
                                {{ $device->last_seen_at?->diffForHumans() ?? __('Belum dipakai') }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('Belum ada perangkat terdaftar.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
