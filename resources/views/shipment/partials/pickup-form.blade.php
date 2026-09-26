{{--
    Form SJ tanpa PCK (A-247): cara jemput, kendaraan master atau plat bebas,
    sopir pengguna atau nama bebas. Dipakai detail RET (SJ jemput, A-248) dan
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
        <div class="col-md-3">
            <label class="form-label small" for="jemput-sopir">{{ __('Sopir') }}</label>
            <select class="form-select form-select-sm" id="jemput-sopir" wire:model.live="jemput.driver_id">
                <option value="">{{ __('Bukan pengguna WMS') }}</option>
                @foreach ($pilihanJemput['drivers'] as $d) <option value="{{ $d->id }}">{{ $d->name }}</option> @endforeach
            </select>
            @if ($jemput['driver_id'] === '')
                <input class="form-control form-control-sm mt-1" type="text" maxlength="100" wire:model="jemput.carried_by_name" placeholder="{{ __('Nama sopir') }}" aria-label="{{ __('Nama sopir') }}">
            @endif
            @error('jemput.carried_by_name') <div class="text-danger small">{{ $message }}</div> @enderror
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
