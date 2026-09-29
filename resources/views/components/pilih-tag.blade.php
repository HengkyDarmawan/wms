{{--
    Kotak pilihan ganda bergaya tag (A-350) — pengganti <select multiple> yang
    menjebak (sekali klik langsung terpilih, sulit dibatalkan). Nilai disimpan
    ke properti Livewire `model` sebagai array, sama seperti select biasa.

    <x-pilih-tag model="conditions.warehouse_ids" id="kondisi-gudang" :options="[id => 'CKG — Gudang Utama']" />
--}}
@props([
    'model',
    'options' => [],
    'id' => null,
    'placeholder' => null,
    'live' => false,
])
@php($id ??= 'tag-'.str_replace(['.', '_'], '-', $model))
{{-- Kunci ikut daftar pilihan: bila pilihannya berubah (mis. bin per gudang), kotak dibuat ulang. --}}
<div wire:ignore wire:key="pilih-tag-{{ $model }}-{{ md5(implode(',', array_keys($options))) }}"
     x-data="pilihTag($wire.entangle(@js($model)){{ $live ? '.live' : '' }})"
     {{ $attributes->merge(['class' => 'nx-pilih-tag']) }}>
    <select x-ref="select" id="{{ $id }}" multiple autocomplete="off"
            data-placeholder="{{ $placeholder ?? __('Klik atau ketik untuk mencari…') }}"
            data-kosong="{{ __('Tidak ada yang cocok') }}"
            data-hapus="{{ __('Hapus') }}">
        @foreach ($options as $nilai => $label)
            <option value="{{ $nilai }}">{{ $label }}</option>
        @endforeach
    </select>
</div>
