{{--
    A-291/A-292: isian "Kemasan lain…" selebar baris dokumen supaya kode kemasan dan angka isi
    terbaca penuh di layar 1366 px maupun HP. Parameter sama dengan unit-picker.
--}}
@if ($opsi !== null && ! $opsi['per_unit'] && ($row['uom'] ?? '') === 'lain')
    <div class="d-flex flex-wrap align-items-center gap-2 small bg-body-tertiary rounded px-2 py-1" data-kemasan-lain>
        <span class="fw-semibold">{{ __('Kemasan lain') }}:</span>
        <span>1</span>
        <select class="form-select form-select-sm w-auto" style="min-width: 7rem" id="{{ $idAwal }}-kemasan-lain" wire:model.live="{{ $prefix }}.uom_lain" aria-label="{{ __('Kemasan') }}">
            <option value="">{{ __('Kemasan…') }}</option>
            @foreach ($satuanLain as $u)
                @continue($u->code === $opsi['base'])
                <option value="{{ $u->id }}">{{ $u->code }}</option>
            @endforeach
        </select>
        <span>=</span>
        <input class="form-control form-control-sm w-auto" style="min-width: 7rem" type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="{{ $prefix }}.uom_factor"
               aria-label="{{ __('Isi kemasan dalam satuan dasar') }}">
        <span>{{ $opsi['base'] }}</span>
        <div class="form-check mb-0 ms-md-2">
            <input class="form-check-input" id="{{ $idAwal }}-ingat" type="checkbox" wire:model="{{ $prefix }}.ingat">
            <label class="form-check-label" for="{{ $idAwal }}-ingat">{{ __('Ingat untuk item ini') }}</label>
        </div>
    </div>
@endif
