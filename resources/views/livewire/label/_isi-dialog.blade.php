{{-- A-299: dialog jumlah isi saat label induk dipindai keluar. Variabel: $labelDialog (PackageLabel|null). --}}
@if ($labelDialog)
    <div class="card border-primary mb-3" wire:key="label-isi-{{ $labelDialog->id }}">
        <div class="card-header"><strong>{{ __('Label induk :kode', ['kode' => $labelDialog->code]) }}</strong></div>
        <div class="card-body">
            <p class="small text-muted mb-2">
                {{ $labelDialog->item?->code }} {{ $labelDialog->item?->name }} ·
                {{ __('isi tersisa') }} {{ \App\Domain\Master\Support\QtyFormat::withUnit($labelDialog->qty_remaining, $labelDialog->item?->baseUom?->code) }}.
                {{ __('Isi berapa yang diambil dari kemasan ini?') }}
            </p>
            <label class="form-label" for="label-isi">{{ __('Jumlah isi diambil') }} <span class="wajib">*</span></label>
            <input class="form-control @error('labelIsi') is-invalid @enderror" id="label-isi" type="number" step="0.0001" min="0"
                   wire:model="labelIsi" wire:keydown.enter.prevent="simpanIsiLabel" autofocus>
            @error('labelIsi') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary" type="button" wire:click="simpanIsiLabel">{{ __('Ambil') }}</button>
            <button class="btn btn-outline-secondary" type="button" wire:click="tutupIsiLabel">{{ __('Tutup') }}</button>
        </div>
    </div>
@endif
