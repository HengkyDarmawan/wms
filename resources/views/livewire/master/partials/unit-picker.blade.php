{{--
    A-291/A-292: pemilih satuan baris dokumen (GRN, Permintaan material, Retur).
    Parameter: $prefix ("rows.3"), $row (isian baris), $opsi (PicksItemUnit::opsiSatuan untuk item baris),
    $satuanLain (daftar satuan), $hasil ("10 DUS = 120 BOX" atau null), $idAwal (awalan id elemen).
    Isian "Kemasan lain…" dirender terpisah selebar baris lewat partial unit-picker-lain.
--}}
@if ($opsi !== null && ! $opsi['per_unit'])
    <div>
        <label class="form-label small" for="{{ $idAwal }}-satuan">{{ __('Satuan') }}</label>
        <select class="form-select form-select-sm" id="{{ $idAwal }}-satuan" wire:model.live="{{ $prefix }}.uom">
            <option value="">{{ $opsi['base'] }} ({{ __('satuan dasar') }})</option>
            @foreach ($opsi['codes'] as $uomId => $kode)
                <option value="{{ $uomId }}">{{ $kode }} ({{ __('isi') }} {{ \App\Domain\Master\Support\QtyFormat::withUnit($opsi['factors'][$uomId], $opsi['base']) }})</option>
            @endforeach
            <option value="lain">{{ __('Kemasan lain…') }}</option>
        </select>
        @if (($row['uom'] ?? '') === 'lain')
            {{-- Isian kemasan baru tampil di baris sendiri: partial unit-picker-lain. --}}
            <div class="form-text">{{ __('Isi kemasan di bawah baris ini.') }}</div>
        @endif
        @if ($hasil !== null)
            <div class="form-text fw-semibold">{{ $hasil }}</div>
        @endif
    </div>
@endif
