{{-- Kartu Undangan & password awal (A-333, A-334): hanya bagi pemegang `user.create`. --}}
@php($nomorWa = \App\Domain\Shared\Messaging\PhoneNumber::normalize($user->phone))

@if ($passwordDibuat !== '')
    <div class="card border-success mb-3">
        <div class="card-header text-success"><strong>{{ __('Password dibuat') }}</strong></div>
        <div class="card-body" x-data="{ salin: false }">
            <p class="mb-2">{{ $user->name }} · {{ $user->email }}</p>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <code class="fs-5">{{ $passwordDibuat }}</code>
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        x-on:click="navigator.clipboard?.writeText(@js($passwordDibuat)); salin = true; setTimeout(() => salin = false, 1500)">
                    <i class="bi" :class="salin ? 'bi-clipboard-check' : 'bi-clipboard'"></i> {{ __('Salin') }}
                </button>
                @if ($nomorWa !== null)
                    <a class="btn btn-sm btn-success" target="_blank" rel="noopener"
                       href="https://wa.me/{{ $nomorWa }}?text={{ rawurlencode(__('Akun :app Anda sudah dibuat. Email: :email — Password: :pass. Silakan ganti password setelah masuk.', ['app' => config('app.name'), 'email' => $user->email, 'pass' => $passwordDibuat])) }}">
                        <i class="bi bi-whatsapp"></i> {{ __('Kirim via WhatsApp') }}
                    </a>
                @endif
            </div>
            <p class="small text-muted mb-0 mt-2">
                {{ __('Password hanya tampil sekali. Undangan yang tertunda sudah dibatalkan; minta pemiliknya mengganti password setelah masuk.') }}
            </p>
        </div>
    </div>
@endif

@if ($undangan !== null && $undangan->isUsable())
    <div class="card border-info mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <strong>{{ __('Undangan — belum dipakai') }}</strong>
            <span class="small text-muted">
                {{ __('Berlaku sampai') }}
                {{ $undangan->expires_at->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i') }}
                · {{ __('dikirim :n×', ['n' => $undangan->sent_count]) }}
            </span>
        </div>
        <div class="card-body">
            @if ($tautan !== '')
                <div class="mb-2" x-data="{ salin: false }">
                    <input class="form-control font-monospace" type="text" readonly value="{{ $tautan }}"
                           aria-label="{{ __('Tautan undangan') }}">
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                x-on:click="navigator.clipboard?.writeText(@js($tautan)); salin = true; setTimeout(() => salin = false, 1500)">
                            <i class="bi" :class="salin ? 'bi-clipboard-check' : 'bi-clipboard'"></i> {{ __('Salin tautan') }}
                        </button>
                        @if ($nomorWa !== null)
                            <a class="btn btn-sm btn-success" target="_blank" rel="noopener"
                               href="https://wa.me/{{ $nomorWa }}?text={{ rawurlencode(__('Akun :app Anda sudah dibuat. Silakan atur password lewat tautan ini (berlaku 72 jam): :url', ['app' => config('app.name'), 'url' => $tautan])) }}">
                                <i class="bi bi-whatsapp"></i> {{ __('Kirim via WhatsApp') }}
                            </a>
                        @endif
                    </div>
                </div>
            @else
                <button class="btn btn-sm btn-outline-primary mb-2" type="button" wire:click="tampilkanTautan">
                    <i class="bi bi-eye"></i> {{ __('Tampilkan tautan undangan') }}
                </button>
            @endif

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="kirimUlangUndangan">
                    <i class="bi bi-arrow-repeat"></i> {{ __('Kirim ulang undangan') }}
                </button>
                @can('resetPassword', $user)
                    <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="mintaPassword">
                        <i class="bi bi-key"></i> {{ __('Buatkan password sekarang') }}
                    </button>
                @endcan
            </div>

            <p class="small text-muted mb-0 mt-2">
                {{ __('Kirim ulang membuat tautan baru — tautan lama langsung mati. Buka tautan di jendela penyamaran, karena Anda sedang masuk sebagai pengguna lain.') }}
            </p>
        </div>
    </div>
@endif

@if ($formPassword)
    <div class="card border-primary mb-3">
        <div class="card-header"><strong>{{ __('Buatkan password untuk') }} {{ $user->name }}</strong></div>
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label" for="pwd-baru">{{ __('Password') }} <span class="wajib">*</span></label>
                <input class="form-control @error('passwordBaru') is-invalid @enderror" id="pwd-baru" type="text"
                       wire:model="passwordBaru" maxlength="100">
                <div class="form-text">{{ __('Minimal 10 karakter. Ditampilkan sekali setelah disimpan.') }}</div>
                @error('passwordBaru') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-7 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-secondary" type="button" wire:click="acakPassword">{{ __('Acak') }}</button>
                <button class="btn btn-primary" type="button" wire:click="simpanPassword">{{ __('Simpan password') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="$set('formPassword', false)">{{ __('Batal') }}</button>
            </div>
        </div>
    </div>
@endif
