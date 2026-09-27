<div class="card mt-3" id="whatsapp">
    <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3"><i class="bi bi-whatsapp" aria-hidden="true"></i> {{ __('WhatsApp') }}</h2>

        @if (! $aktif)
            <p class="small text-muted mb-2">{{ __('WhatsApp belum aktif untuk company ini. Nomor tetap bisa disimpan oleh Admin Company, tetapi baru dipakai setelah diverifikasi.') }}</p>
            @if ($tersamar)
                <p class="small mb-0">{{ __('Nomor tersimpan') }}: {{ $tersamar }} @if ($terverifikasi) <span class="badge text-bg-success">{{ __('terverifikasi') }}</span> @endif</p>
            @endif
        @else
            <p class="small text-muted">{{ __('Nomor ini menerima notifikasi dan tombol approval lewat WhatsApp. Nomor harus diverifikasi dengan kode; tombol approval hanya diterima dari nomor ini.') }}</p>

            @if ($terverifikasi && ! $menungguKode)
                <p class="mb-2">{{ $tersamar }} <span class="badge text-bg-success">{{ __('terverifikasi') }}</span></p>
            @endif

            <div class="mb-2">
                <label class="form-label" for="wa-phone">{{ __('Nomor WhatsApp') }}</label>
                <div class="input-group">
                    <input class="form-control @error('phone') is-invalid @enderror" id="wa-phone" type="text" inputmode="tel" maxlength="20" wire:model="phone" placeholder="0812…">
                    <button class="btn btn-outline-primary" type="button" wire:click="kirimKode">{{ $terverifikasi ? __('Ganti & kirim kode') : __('Kirim kode') }}</button>
                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            @if ($menungguKode)
                <div class="mb-2">
                    <label class="form-label" for="wa-code">{{ __('Kode dari WhatsApp') }} <span class="wajib">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('code') is-invalid @enderror" id="wa-code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code">
                        <button class="btn btn-primary" type="button" wire:click="verifikasi">{{ __('Verifikasi') }}</button>
                        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-text">{{ __('Kode berlaku 10 menit.') }}</div>
                </div>
            @endif

            @if ($tersamar)
                <button class="btn btn-link btn-sm text-danger p-0" type="button" wire:click="hapus" wire:confirm="{{ __('Hapus nomor WhatsApp? Pesan WhatsApp berhenti untuk Anda.') }}">{{ __('Hapus nomor') }}</button>
            @endif
        @endif
    </div>
</div>
