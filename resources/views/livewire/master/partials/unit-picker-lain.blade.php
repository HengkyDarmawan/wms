{{--
    A-291/A-292: isian "Kemasan lain…" selebar baris dokumen supaya kode kemasan dan angka isi
    terbaca penuh di layar 1366 px maupun HP. Parameter sama dengan unit-picker.
    A-357: dibaca sebagai kalimat "1 DUS berisi 40 PACK = 480 BOX" — satuan isi boleh satuan dasar
    atau kemasan aktif item; isi dihitung ke satuan dasar sebelum dikirim ke aksi.
--}}
@if ($opsi !== null && ! $opsi['per_unit'] && ($row['uom'] ?? '') === 'lain')
    @php($isiPerKemasan = \App\Domain\Master\Support\UnitInput::faktorLain($row, $opsi))
    <div class="d-flex flex-wrap align-items-center gap-2 small bg-body-tertiary rounded px-2 py-1" data-kemasan-lain>
        <span class="fw-semibold">{{ __('Kemasan lain') }}:</span>
        <span>1</span>
        <select class="form-select form-select-sm w-auto" style="min-width: 7rem" id="{{ $idAwal }}-kemasan-lain" wire:model.live="{{ $prefix }}.uom_lain" aria-label="{{ __('Kemasan') }}">
            <option value="">{{ __('Kemasan…') }}</option>
            @foreach ($satuanLain as $u)
                @continue($u->code === $opsi['base'] || isset($opsi['codes'][$u->id]))
                <option value="{{ $u->id }}">{{ $u->code }}</option>
            @endforeach
        </select>
        <span>{{ __('berisi') }}</span>
        <input class="form-control form-control-sm w-auto" style="min-width: 6rem" type="text" inputmode="decimal" wire:model.live.debounce.500ms="{{ $prefix }}.uom_factor"
               aria-label="{{ __('Jumlah isi') }}">
        <select class="form-select form-select-sm w-auto" style="min-width: 6rem" id="{{ $idAwal }}-kemasan-isi" wire:model.live="{{ $prefix }}.uom_isi" aria-label="{{ __('Satuan isi') }}">
            <option value="">{{ $opsi['base'] }}</option>
            @foreach ($opsi['codes'] as $uomId => $kode)
                <option value="{{ $uomId }}">{{ $kode }}</option>
            @endforeach
        </select>
        @if ($isiPerKemasan > 0 && ($row['uom_isi'] ?? '') !== '')
            <span class="text-nowrap">= <strong>{{ \App\Domain\Master\Support\QtyFormat::withUnit($isiPerKemasan, $opsi['base']) }}</strong></span>
        @endif
        <div class="form-check mb-0 ms-md-2">
            <input class="form-check-input" id="{{ $idAwal }}-ingat" type="checkbox" wire:model="{{ $prefix }}.ingat">
            <label class="form-check-label" for="{{ $idAwal }}-ingat">{{ __('Ingat untuk item ini') }}</label>
        </div>
    </div>
@endif
