{{-- A-320: panel objek denah terpilih (tanpa stok). Variabel: $objekPilih, $jenisObjek. --}}
<div class="card mb-3 border-primary" data-panel-objek>
    <div class="card-header d-flex justify-content-between align-items-start">
        <div>
            <div class="fw-semibold fs-6 text-body" style="text-transform: none; letter-spacing: normal">{{ $objekPilih['name'] }}</div>
            <div class="small text-muted">{{ $objekPilih['label'] }} · {{ __('tanpa stok — tidak menyentuh kartu stok') }}</div>
        </div>
        <button class="btn-close" type="button" wire:click="tutupRak" aria-label="{{ __('Tutup') }}"></button>
    </div>
    <div class="card-body small">
        @if ($edit)
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label mb-0" for="obj-jenis">{{ __('Jenis') }}</label>
                    <select class="form-select form-select-sm" id="obj-jenis" wire:model="formObjek.object_type">
                        @foreach ($jenisObjek as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label mb-0" for="obj-nama">{{ __('Nama') }}</label>
                    <input class="form-control form-control-sm @error('formObjek.name') is-invalid @enderror" id="obj-nama" type="text" maxlength="60" wire:model="formObjek.name">
                    @error('formObjek.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                @foreach (['pos_x' => 'X', 'pos_y' => 'Y', 'length_m' => __('Panjang'), 'width_m' => __('Lebar')] as $k => $t)
                    <div class="col-3">
                        <label class="form-label mb-0" for="obj-{{ $k }}">{{ $t }}</label>
                        <input class="form-control form-control-sm @error('formObjek.'.$k) is-invalid @enderror" id="obj-{{ $k }}" type="number" step="0.1" min="0" wire:model="formObjek.{{ $k }}">
                        @error('formObjek.'.$k) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach
            </div>
            <div class="text-muted mt-1">{{ __('Meter; posisi dari sudut kiri-atas gedung. Rotasi') }} {{ $objekPilih['rotation'] }}°.</div>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <button class="btn btn-sm btn-primary" type="button" wire:click="simpanObjek">{{ __('Simpan') }}</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="putar"><i class="bi bi-arrow-clockwise"></i> {{ __('Putar 90°') }}</button>
                <button class="btn btn-sm btn-outline-danger ms-auto" type="button" wire:click="nonaktifkan" wire:confirm="{{ __('Nonaktifkan objek ini dari denah?') }}">{{ __('Nonaktifkan') }}</button>
            </div>
        @else
            <div>X {{ $angka($objekPilih['x']) }} m · Y {{ $angka($objekPilih['y']) }} m · {{ $angka($objekPilih['p']) }} × {{ $angka($objekPilih['l']) }} m</div>
        @endif
    </div>
</div>
