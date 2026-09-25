@extends('layouts.auth')

@section('title', __('Bukti terima'))

@section('content')
    {{-- A-231: langkah 1 halaman penerima bertoken — OTP disampaikan driver (O-15). --}}
    <h1 class="h4 mb-1">{{ __('Bukti terima') }} {{ $sj?->number }}</h1>
    <p class="text-muted mb-3">
        {{ __('Dari') }} {{ $sj?->warehouse?->name }}
        @if ($sj?->destinationProject) · {{ __('untuk') }} {{ $sj->destinationProject->name }} @endif
    </p>

    @if ($terkunci)
        <div class="alert alert-danger">{{ __('Tautan ini terkunci karena terlalu banyak percobaan. Minta driver menerbitkan tautan baru.') }}</div>
    @else
        <p class="small text-muted">{{ __('Masukkan 6 digit kode OTP yang disampaikan driver untuk membuka formulir.') }}</p>
        <form method="post" action="{{ route('terima.otp', $token) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="otp">{{ __('Kode OTP') }} <span class="wajib">*</span></label>
                <input class="form-control form-control-lg text-center @error('otp') is-invalid @enderror" id="otp" name="otp"
                       type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" autofocus>
                @error('otp') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button class="btn btn-primary w-100" type="submit">{{ __('Buka formulir') }}</button>
        </form>
    @endif
@endsection
