@extends('layouts.auth')

@section('title', __('Bukti terima'))

@section('content')
    {{-- A-231: langkah 1 halaman penerima bertoken — OTP dikirim ke HP penerima (A-273) atau disampaikan driver. --}}
    <h1 class="h4 mb-1">{{ __('Bukti terima') }} {{ $sj?->number }}</h1>
    <p class="text-muted mb-3">
        {{ __('Dari') }} {{ $sj?->warehouse?->name }}
        @if ($sj?->destinationProject) · {{ __('untuk') }} {{ $sj->destinationProject->name }} @endif
    </p>

    @if ($terkunci)
        <div class="alert alert-danger">{{ __('Tautan ini terkunci karena terlalu banyak percobaan. Minta driver menerbitkan tautan baru.') }}</div>
    @else
        @if (session('status'))
            <div class="alert alert-success small">{{ session('status') }}</div>
        @endif
        <p class="small text-muted">
            {{ $terkirimKe
                ? __('Masukkan 6 digit kode OTP yang dikirim ke WhatsApp/SMS :hp untuk membuka formulir.', ['hp' => $terkirimKe])
                : __('Masukkan 6 digit kode OTP yang disampaikan driver untuk membuka formulir.') }}
        </p>
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
        @if ($bolehKirimUlang)
            <form class="mt-2" method="post" action="{{ route('terima.resend', $token) }}">
                @csrf
                <button class="btn btn-link w-100" type="submit">{{ __('Kode belum masuk? Kirim ulang') }}</button>
            </form>
        @endif
    @endif
@endsection
