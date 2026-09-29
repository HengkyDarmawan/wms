{{--
    Pilihan tunggal yang bisa dicari (A-358, Tom Select) — pola sama dengan <x-pilih-tag>.
    Nilai disimpan ke properti Livewire `model`; '' = null. Pembungkus luar (bukan
    wire:ignore) memegang tanda galat supaya ikut berubah saat validasi.

    <x-pilih model="managerId" id="managerId" kosong="— Ikuti jabatan —"
             :options="[['value' => 5, 'text' => 'Budi', 'badge' => 'Direktur', 'sub' => 'Manajemen']]" />

    `options`: daftar ['value', 'text', 'badge'?, 'sub'?] atau peta nilai => teks.
    Kosong/tanpa badge → tanpa badge; tanpa sub → tanpa teks kecil.
--}}
@props([
    'model',
    'options' => [],
    'id' => null,
    'kosong' => null,
    'placeholder' => null,
    'live' => false,
])
@php
    $id ??= 'pilih-'.str_replace(['.', '_'], '-', $model);
    $daftar = collect($options)->map(fn ($o, $k) => is_array($o) ? $o : ['value' => $k, 'text' => $o])->values();
@endphp
<div {{ $attributes->class(['nx-pilih', 'is-invalid' => $errors->has($model)]) }}>
    {{-- Kunci ikut daftar pilihan: bila pilihannya berubah, kotak dibuat ulang. --}}
    <div wire:ignore wire:key="pilih-{{ $model }}-{{ md5($daftar->toJson()) }}"
         x-data="pilih($wire.entangle(@js($model)){{ $live ? '.live' : '' }})">
        <select x-ref="select" id="{{ $id }}" autocomplete="off"
                data-placeholder="{{ $placeholder ?? __('Klik atau ketik untuk mencari…') }}"
                data-kosong="{{ __('Tidak ada yang cocok') }}">
            @if ($kosong !== null)
                <option value="">{{ $kosong }}</option>
            @endif
            @foreach ($daftar as $o)
                {{-- data-badge / data-sub dibaca Tom Select lewat dataset (ikut dicari). --}}
                <option value="{{ $o['value'] }}"
                        @if (filled($o['badge'] ?? null)) data-badge="{{ $o['badge'] }}" @endif
                        @if (filled($o['sub'] ?? null)) data-sub="{{ $o['sub'] }}" @endif>{{ $o['text'] }}</option>
            @endforeach
        </select>
    </div>
    @error($model) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>
