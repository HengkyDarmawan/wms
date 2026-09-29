{{-- ⑤ Cara masuk pertama kali (A-334): undangan, atau password yang dibuatkan Admin. --}}
<div class="card mb-3">
    <div class="card-header"><strong>{{ __('5. Cara ia masuk pertama kali') }}</strong></div>
    <div class="card-body">
        <div class="form-check">
            <input class="form-check-input" type="radio" id="cara-undangan" value="undangan" wire:model.live="caraMasuk">
            <label class="form-check-label" for="cara-undangan">
                {{ __('Kirim undangan — ia mengatur passwordnya sendiri') }}
                <div class="small text-muted">
                    {{ __('Tautannya bisa Anda salin atau kirim lewat WhatsApp setelah disimpan.') }}
                </div>
            </label>
        </div>

        <div class="form-check mt-2">
            <input class="form-check-input" type="radio" id="cara-password" value="password" wire:model.live="caraMasuk">
            <label class="form-check-label" for="cara-password">
                {{ __('Buatkan passwordnya sekarang') }}
                <div class="small text-muted">
                    {{ __('Tanpa undangan. Serahkan passwordnya langsung, lalu minta ia menggantinya.') }}
                </div>
            </label>
        </div>

        @if ($caraMasuk === 'password')
            <div class="row g-2 align-items-end mt-2">
                <div class="col-md-4">
                    <label class="form-label" for="passwordAwal">{{ __('Password') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('passwordAwal') is-invalid @enderror" id="passwordAwal"
                           type="text" wire:model="passwordAwal" maxlength="100">
                    <div class="form-text">{{ __('Minimal 10 karakter. Ditampilkan sekali setelah disimpan.') }}</div>
                    @error('passwordAwal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-secondary" type="button" wire:click="acakPassword">
                        {{ __('Acak') }}
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
