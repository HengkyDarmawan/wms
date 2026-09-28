{{--
    Form SJ tanpa PCK (A-247): cara jemput, kendaraan master atau plat bebas,
    nama & HP sopir teks (A-311). Dipakai detail RET (SJ jemput, A-248) dan
    detail TRF aset (SJ antar site, A-249). Properti Livewire: $jemput.
    Variabel: $judul, $keterangan, $aksi (metode Livewire), $pilihanJemput.
--}}
<div class="card mb-3">
    <div class="card-header"><strong>{{ $judul }}</strong> <span class="small text-muted">{{ $keterangan }}</span></div>
    <div class="card-body row g-2">
        <div class="col-md-3">
            <label class="form-label small" for="jemput-cara">{{ __('Cara jemput') }}</label>
            <select class="form-select form-select-sm" id="jemput-cara" wire:model.live="jemput.shipment_method">
                <option value="own_fleet">{{ __('Kendaraan sendiri') }}</option>
                <option value="carrier">{{ __('Ekspedisi') }}</option>
            </select>
            @error('jemput.shipment_method') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        @if ($jemput['shipment_method'] === 'carrier')
            <div class="col-md-3">
                <label class="form-label small" for="jemput-ekspedisi">{{ __('Ekspedisi') }}</label>
                <select class="form-select form-select-sm" id="jemput-ekspedisi" wire:model="jemput.carrier_id">
                    <option value="">—</option>
                    @foreach ($pilihanJemput['carriers'] as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                </select>
                @error('jemput.carrier_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        @endif
        <div class="col-md-3">
            <label class="form-label small" for="jemput-kendaraan">{{ __('Kendaraan') }}</label>
            <select class="form-select form-select-sm" id="jemput-kendaraan" wire:model.live="jemput.vehicle_id">
                <option value="">{{ __('Bukan kendaraan terdaftar') }}</option>
                @foreach ($pilihanJemput['vehicles'] as $v) <option value="{{ $v->id }}">{{ $v->plate_no }}</option> @endforeach
            </select>
            @if ($jemput['vehicle_id'] === '')
                <input class="form-control form-control-sm mt-1" type="text" maxlength="20" wire:model="jemput.vehicle_plate" placeholder="{{ __('Plat, mis. B 1234 XY') }}" aria-label="{{ __('Plat kendaraan') }}">
            @endif
            @error('jemput.vehicle_plate') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        {{-- A-311: sopir tidak punya akun — nama & HP diketik (terisi dari driver bawaan kendaraan). --}}
        <div class="col-md-3">
            <label class="form-label small" for="jemput-sopir">{{ __('Nama sopir') }} <span class="wajib">*</span></label>
            <input class="form-control form-control-sm @error('jemput.driver_name') is-invalid @enderror" id="jemput-sopir" type="text" maxlength="100" wire:model="jemput.driver_name">
            @error('jemput.driver_name') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label small" for="jemput-hp">{{ __('No. HP sopir') }} @if ($jemput['shipment_method'] === 'own_fleet') <span class="wajib">*</span> @endif</label>
            <input class="form-control form-control-sm @error('jemput.driver_phone') is-invalid @enderror" id="jemput-hp" type="text" inputmode="tel" maxlength="20" wire:model="jemput.driver_phone" placeholder="08…">
            @error('jemput.driver_phone') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label small" for="jemput-catatan">{{ __('Catatan') }}</label>
            <input class="form-control form-control-sm" id="jemput-catatan" type="text" maxlength="255" wire:model="jemput.notes" placeholder="{{ __('Opsional') }}">
        </div>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary" type="button" wire:click="{{ $aksi }}">{{ $judul }}</button>
    </div>
</div>
