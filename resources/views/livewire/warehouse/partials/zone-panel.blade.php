{{-- A-320: panel zona terpilih (mode Atur denah). Variabel: $zonaPilih, $formZona, $alasan. --}}
@php($zid = $zonaPilih['id'])
<div class="card mb-3 border-primary" data-panel-zona>
    <div class="card-header d-flex justify-content-between align-items-start">
        <div>
            <div class="fw-semibold fs-6 text-body" style="text-transform: none; letter-spacing: normal">{{ __('Zona') }} {{ $zonaPilih['code'] }} — {{ $zonaPilih['name'] }}</div>
            <div class="small text-muted">{{ count($zonaPilih['racks']) }} {{ __('rak') }} · {{ $zonaPilih['otomatis'] ? __('posisi ditata otomatis') : 'X '.$angka($zonaPilih['x']).' m · Y '.$angka($zonaPilih['y']).' m' }}</div>
        </div>
        <button class="btn-close" type="button" wire:click="tutupRak" aria-label="{{ __('Tutup') }}"></button>
    </div>
    <div class="card-body small">
        @if ($edit)
            <div class="text-muted mb-2">{{ __('Kode zona terkunci (BR-WH-01).') }}</div>
            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label mb-0" for="zona-n-{{ $zid }}">{{ __('Nama zona') }}</label>
                    <input class="form-control form-control-sm @error('formZona.'.$zid.'.name') is-invalid @enderror" id="zona-n-{{ $zid }}" type="text" maxlength="60" wire:model="formZona.{{ $zid }}.name">
                    @error('formZona.'.$zid.'.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                @foreach (['length_m' => __('Panjang (m)'), 'width_m' => __('Lebar (m)'), 'pos_x' => __('X di gedung (m)'), 'pos_y' => __('Y di gedung (m)')] as $k => $t)
                    <div class="col-6">
                        <label class="form-label mb-0" for="zona-{{ $k }}-{{ $zid }}">{{ $t }}</label>
                        <input class="form-control form-control-sm @error('formZona.'.$zid.'.'.$k) is-invalid @enderror" id="zona-{{ $k }}-{{ $zid }}" type="number" step="0.5" min="0" wire:model="formZona.{{ $zid }}.{{ $k }}" placeholder="{{ __('opsional') }}">
                        @error('formZona.'.$zid.'.'.$k) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach
            </div>
            <button class="btn btn-sm btn-primary mt-2" type="button" wire:click="simpanZona({{ $zid }})">{{ __('Simpan zona') }}</button>

            <hr>
            <div class="fw-semibold mb-1">{{ __('Nonaktifkan zona') }}</div>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <div>
                    <select class="form-select form-select-sm @error('nonaktif.reason') is-invalid @enderror" wire:model="alasanNonaktif" aria-label="{{ __('Alasan nonaktif') }}">
                        <option value="">{{ __('Alasan…') }}</option>
                        @foreach ($alasan as $kode => $label) <option value="{{ $kode }}">{{ $label }}</option> @endforeach
                    </select>
                    @error('nonaktif.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button class="btn btn-sm btn-outline-danger" type="button" wire:click="nonaktifkan" wire:confirm="{{ __('Nonaktifkan zona ini?') }}">{{ __('Nonaktifkan') }}</button>
            </div>
            <div class="text-muted mt-1">{{ __('Hanya bila semua raknya sudah nonaktif.') }}</div>
        @else
            <div>{{ $zonaPilih['length_m'] !== null ? $angka($zonaPilih['length_m']).' × '.$angka($zonaPilih['width_m'] ?? 0).' m' : __('ukuran belum diisi') }}</div>
        @endif
    </div>
</div>
