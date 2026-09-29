{{--
    BR-WH-10 (A-367): Kepala Gudang (izin setujui penyesuaian) boleh menaruh barang lain di
    bin yang Khusus Barang Ini dengan alasan — berlaku untuk semua baris di layar ini dan
    dicatat per bin. Kosong = bin khusus tetap menolak. Properti Livewire: `bukaKhusus`.
--}}
@can('adjustment.approve')
    <details class="mb-3 small" @if (filled($this->bukaKhusus ?? '')) open @endif data-buka-khusus>
        <summary class="text-muted">{{ __('Buka tempat khusus (Kepala Gudang)') }}</summary>
        <label class="form-label mt-2 mb-1" for="buka-khusus-{{ $this->getId() }}">{{ __('Alasan menaruh barang lain di bin yang Khusus Barang Ini') }}</label>
        <input class="form-control form-control-sm" id="buka-khusus-{{ $this->getId() }}" type="text" maxlength="255" wire:model="bukaKhusus"
               placeholder="{{ __('Kosongkan bila tidak perlu — bin khusus tetap menolak barang lain') }}">
        <div class="form-text">{{ __('Alasan dicatat per bin yang dibuka, bersama nama Anda dan nomor dokumen.') }}</div>
    </details>
@endcan
