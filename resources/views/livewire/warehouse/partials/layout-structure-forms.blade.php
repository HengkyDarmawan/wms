{{-- A-271/A-255: tambah zona, rak (+ level + bin), dan rak area dari denah (mode Atur). --}}
    {{-- A-271: bangun struktur langsung dari denah — zona, lalu rak dengan level & bin sekaligus. --}}
    <div class="card mb-3" id="tambah-struktur">
        <div class="card-header"><strong>{{ __('Tambah zona & rak') }}</strong> <span class="small text-muted">{{ __('kode tidak bisa diubah setelah dibuat karena membentuk kode bin') }}</span></div>
        <div class="card-body small">
            <div class="fw-semibold mb-1">{{ __('Zona baru') }}</div>
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label mb-0" for="zb-kode">{{ __('Kode zona') }} <span class="wajib">*</span></label>
                    <input class="form-control form-control-sm @error('formZonaBaru.code') is-invalid @enderror" id="zb-kode" type="text" maxlength="10" wire:model="formZonaBaru.code" placeholder="mis. C">
                    @error('formZonaBaru.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label mb-0" for="zb-nama">{{ __('Nama zona') }} <span class="wajib">*</span></label>
                    <input class="form-control form-control-sm @error('formZonaBaru.name') is-invalid @enderror" id="zb-nama" type="text" maxlength="60" wire:model="formZonaBaru.name" placeholder="{{ __('mis. Zona besi') }}">
                    @error('formZonaBaru.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4 d-flex align-items-end"><button class="btn btn-sm btn-primary" type="button" wire:click="tambahZona">{{ __('Tambah zona') }}</button></div>
            </div>

            <div class="fw-semibold mb-1">{{ __('Rak baru') }}</div>
            <div class="row g-2">
                <div class="col-md-2">
                    <label class="form-label mb-0" for="rb-zona">{{ __('Zona') }} <span class="wajib">*</span></label>
                    <select class="form-select form-select-sm @error('formRakBaru.zone_id') is-invalid @enderror" id="rb-zona" wire:model="formRakBaru.zone_id">
                        <option value="">—</option>
                        @foreach ($denah['zones'] as $z) <option value="{{ $z['id'] }}">{{ $z['code'] }}</option> @endforeach
                    </select>
                    @error('formRakBaru.zone_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-0" for="rb-kode">{{ __('Kode rak') }} <span class="wajib">*</span></label>
                    <input class="form-control form-control-sm @error('formRakBaru.code') is-invalid @enderror" id="rb-kode" type="text" maxlength="10" wire:model="formRakBaru.code" placeholder="mis. R05">
                    @error('formRakBaru.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3"><label class="form-label mb-0" for="rb-nama">{{ __('Nama rak') }}</label><input class="form-control form-control-sm" id="rb-nama" type="text" maxlength="60" wire:model="formRakBaru.name" placeholder="{{ __('opsional') }}"></div>
                <div class="col-md-1">
                    <label class="form-label mb-0" for="rb-level">{{ __('Level') }}</label>
                    <input class="form-control form-control-sm @error('formRakBaru.levels') is-invalid @enderror" id="rb-level" type="number" min="1" max="{{ \App\Domain\Warehouse\Actions\SaveWarehouseLayout::MAKS_LEVEL }}" wire:model="formRakBaru.levels">
                    @error('formRakBaru.levels') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-0" for="rb-bin">{{ __('Bin per level') }}</label>
                    <input class="form-control form-control-sm @error('formRakBaru.bins_per_level') is-invalid @enderror" id="rb-bin" type="number" min="0" max="{{ \App\Domain\Warehouse\Actions\SaveWarehouseLayout::MAKS_BIN_PER_LEVEL }}" wire:model="formRakBaru.bins_per_level">
                    @error('formRakBaru.bins_per_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2"><label class="form-label mb-0" for="rb-kap">{{ __('Kapasitas bin') }}</label><input class="form-control form-control-sm" id="rb-kap" type="number" min="0" step="any" wire:model="formRakBaru.capacity_qty" placeholder="{{ __('opsional') }}"></div>
            </div>
            <div class="text-muted mt-1">{{ __('Level diberi kode L1, L2, …; bin B01, B02, … per level. Rak baru ditata otomatis — geser untuk memindahkan.') }}</div>
        </div>
        <div class="card-footer"><button class="btn btn-sm btn-primary" type="button" wire:click="tambahRak">{{ __('Tambah rak') }}</button></div>
    </div>

    {{-- A-255: rak area untuk alat berat — satu bin mewakili seluruh rak atau zona. --}}
    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Rak area untuk barang besar (alat berat)') }}</strong> <span class="small text-muted">{{ __('satu bin untuk seluruh rak atau seluruh zona; isi kedua ditolak') }}</span></div>
        <div class="card-body row g-2 small">
            <div class="col-md-3">
                <label class="form-label mb-0" for="area-zona">{{ __('Zona') }}</label>
                <select class="form-select form-select-sm @error('formArea.zone_id') is-invalid @enderror" id="area-zona" wire:model="formArea.zone_id">
                    <option value="">—</option>
                    @foreach ($denah['zones'] as $z) <option value="{{ $z['id'] }}">{{ $z['code'] }}</option> @endforeach
                </select>
                @error('formArea.zone_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0" for="area-kode">{{ __('Kode rak') }}</label>
                <input class="form-control form-control-sm @error('formArea.code') is-invalid @enderror" id="area-kode" type="text" maxlength="10" wire:model="formArea.code" placeholder="mis. AB1">
                @error('formArea.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3"><label class="form-label mb-0" for="area-nama">{{ __('Nama') }}</label><input class="form-control form-control-sm" id="area-nama" type="text" maxlength="60" wire:model="formArea.name" placeholder="{{ __('mis. Parkir excavator') }}"></div>
            <div class="col-md-2"><label class="form-label mb-0" for="area-kap">{{ __('Kapasitas (unit)') }}</label><input class="form-control form-control-sm" id="area-kap" type="number" min="1" step="1" wire:model="formArea.capacity_qty"></div>
            <div class="col-md-2 d-flex align-items-end">
                <label class="form-check mb-1"><input class="form-check-input" type="checkbox" value="1" wire:model="formArea.seluruh_zona"> <span class="form-check-label">{{ __('Seluruh zona') }}</span></label>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-sm btn-primary" type="button" wire:click="buatArea">{{ __('Buat rak area') }}</button></div>
    </div>
