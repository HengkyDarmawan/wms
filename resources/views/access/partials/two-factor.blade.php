{{--
    Pengaturan verifikasi dua langkah (10-access §6.2).

    Tiga keadaan: belum diatur, rahasia sudah dibuat tetapi belum dikonfirmasi,
    dan sudah aktif. Kode pemulihan hanya ditampilkan sekali, tepat setelah
    dikonfirmasi atau diganti.
--}}
<div class="card mb-3">
    <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Verifikasi dua langkah') }}</h2>

        @error('two_factor')
            <div class="alert alert-danger py-2 small" role="alert">{{ $message }}</div>
        @enderror

        @if (session('recovery_codes'))
            <div class="alert alert-warning" role="alert">
                <div class="fw-semibold mb-2">{{ __('Simpan kode pemulihan ini sekarang.') }}</div>
                <p class="small mb-2">
                    {{ __('Kode ini hanya ditampilkan sekali dan masing-masing hanya bisa dipakai satu kali, untuk masuk bila Anda kehilangan perangkat autentikator.') }}
                </p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach (session('recovery_codes') as $kode)
                        <code class="border rounded px-2 py-1">{{ $kode }}</code>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($user->hasTwoFactorEnabled())
            <p class="mb-2">
                <span class="badge text-bg-success">{{ __('Aktif') }}</span>
                <span class="text-muted small ms-2">
                    {{ __('Sejak :tanggal', ['tanggal' => $user->two_factor_confirmed_at?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i')]) }}
                </span>
            </p>

            <p class="small text-muted">
                {{ __('Sisa kode pemulihan: :jumlah', ['jumlah' => $sisaKodePemulihan]) }}
            </p>

            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('profile.two-factor.regenerate') }}">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm" type="submit">
                        {{ __('Ganti kode pemulihan') }}
                    </button>
                </form>
            </div>

            <hr>

            <form method="POST" action="{{ route('profile.two-factor.disable') }}" novalidate>
                @csrf
                <label class="form-label" for="disable_password">
                    {{ __('Matikan dua langkah — masukkan password Anda') }} <span class="wajib">*</span>
                </label>
                <div class="d-flex gap-2">
                    <input class="form-control @error('current_password') is-invalid @enderror"
                           type="password" id="disable_password" name="current_password"
                           autocomplete="current-password" required>
                    <button class="btn btn-outline-danger" type="submit">{{ __('Matikan') }}</button>
                </div>
                @error('current_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </form>
        @elseif (session('access.2fa.setup_qr'))
            <p class="small text-muted">
                {{ __('Pindai kode berikut dengan aplikasi autentikator, lalu masukkan enam digit yang muncul.') }}
            </p>

            <div class="mb-3">{!! session('access.2fa.setup_qr') !!}</div>

            <p class="small text-muted">
                {{ __('Tidak bisa memindai? Masukkan kunci ini secara manual:') }}
                <code>{{ session('access.2fa.setup_secret') }}</code>
            </p>

            <form method="POST" action="{{ route('profile.two-factor.confirm') }}" novalidate>
                @csrf
                <label class="form-label" for="two_factor_code">
                    {{ __('Kode verifikasi') }} <span class="wajib">*</span>
                </label>
                <div class="d-flex gap-2">
                    <input class="form-control @error('code') is-invalid @enderror" style="max-width: 10rem"
                           type="text" id="two_factor_code" name="code" inputmode="numeric"
                           autocomplete="one-time-code" required>
                    <button class="btn btn-primary" type="submit">{{ __('Aktifkan') }}</button>
                </div>
                @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </form>

            <form class="mt-2" method="POST" action="{{ route('profile.two-factor.cancel') }}">
                @csrf
                <button class="btn btn-link btn-sm px-0" type="submit">{{ __('Batalkan pengaturan') }}</button>
            </form>
        @else
            <p class="small text-muted">
                {{ __('Menambah satu lapis keamanan: setelah password benar, Anda diminta kode dari aplikasi autentikator di ponsel.') }}
            </p>

            <form method="POST" action="{{ route('profile.two-factor.begin') }}">
                @csrf
                <button class="btn btn-outline-primary" type="submit">{{ __('Atur dua langkah') }}</button>
            </form>
        @endif
    </div>
</div>
