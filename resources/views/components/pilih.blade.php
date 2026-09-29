{{--
    Pilihan tunggal yang bisa dicari (A-358, A-383, A-384; Tom Select). Nilai disimpan ke
    properti Livewire `model`; '' = null. Pembungkus luar (bukan wire:ignore) memegang label,
    tanda galat, dan pesan galat supaya ikut berubah saat validasi.

    <x-pilih model="managerId" :label="__('Atasan langsung')" kosong="— Ikuti jabatan —"
             :options="[['value' => 5, 'text' => 'Budi', 'badge' => 'Direktur', 'sub' => 'Manajemen', 'group' => 'Unit ini']]" />

    `options`: daftar ['value', 'text', 'badge'?, 'sub'?, 'group'?] atau peta nilai => teks.
      - `group` → kelompok (optgroup), urut sesuai `kelompok` lalu kemunculan pertama.
    `server`: daftar besar dicari ke server lewat `cariPilihan` (trait CariPilihan) — `options`
      cukup isian awal + nilai terpilih (Pilihan::awalDengan). `kunci` = penanda daftar induk
      (mis. id gudang) supaya kotak dibuat ulang bila induknya berganti.
    `live` (wire:model.live), `disabled`, `kecil` (form-select-sm), `dialog` (dropdown ditempel
      ke <body>, di atas modal), `label` + `wajib` (label terhubung ke kotak cari; aria),
      `aria` (aria-label bila tanpa label terlihat, mis. sel tabel).
--}}
@props([
    'model',
    'options' => [],
    'id' => null,
    'kosong' => null,
    'placeholder' => null,
    'live' => false,
    'server' => false,
    'kunci' => '',
    'label' => null,
    'wajib' => false,
    'disabled' => false,
    'kecil' => false,
    'dialog' => false,
    'kelompok' => [],
    'aria' => null,
])
@php
    $id ??= 'pilih-'.str_replace(['.', '_'], '-', $model);
    $daftar = collect($options)->map(fn ($o, $k) => is_array($o) ? $o : ['value' => $k, 'text' => $o])->values();
    $tanpaKelompok = $daftar->filter(fn ($o) => blank($o['group'] ?? null));
    // `kelompok` = urutan kelompok yang tetap (boleh kosong, supaya hasil cari server masuk ke urutan yang benar).
    $kelompok = collect($kelompok)->mapWithKeys(fn ($k) => [$k => collect()])
        ->merge($daftar->filter(fn ($o) => filled($o['group'] ?? null))->groupBy('group'));
    // Mode server: daftar berubah setiap nilai dipilih (label terpilih), jadi kotak hanya dibuat
    // ulang bila induknya berganti (`kunci`). Mode biasa: ikut isi daftar.
    $hash = $server ? md5($kunci.'|'.($disabled ? 1 : 0)) : md5($daftar->toJson().'|'.($disabled ? 1 : 0));
    $galat = $errors->has($model);
    // data-badge / data-sub dibaca Tom Select lewat dataset (ikut dicari).
    $opsiHtml = fn (array $o) => '<option value="'.e($o['value']).'"'
        .(filled($o['badge'] ?? null) ? ' data-badge="'.e($o['badge']).'"' : '')
        .(filled($o['sub'] ?? null) ? ' data-sub="'.e($o['sub']).'"' : '')
        .'>'.e($o['text']).'</option>';
@endphp
<div {{ $attributes->class(['nx-pilih', 'nx-pilih-sm' => $kecil, 'is-invalid' => $galat]) }}>
    @if ($label !== null)
        <label class="form-label{{ $kecil ? ' small' : '' }}" id="{{ $id }}-label" for="{{ $id }}-ts-control">
            {{ $label }}@if ($wajib) <span class="wajib">*</span>@endif
        </label>
    @endif
    <div wire:ignore wire:key="pilih-{{ $model }}-{{ $hash }}"
         x-data="pilih($wire.entangle(@js($model)){{ $live ? '.live' : '' }})">
        <select x-ref="select" id="{{ $id }}" autocomplete="off" @disabled($disabled)
                data-model="{{ $model }}"
                @if ($server) data-server="1" data-min="{{ \App\Domain\Shared\Pilihan\Pilihan::MIN_HURUF }}" @endif
                @if ($dialog) data-dialog="1" @endif
                @if ($aria !== null) data-aria="{{ $aria }}" @endif
                data-placeholder="{{ $placeholder ?? ($server ? __('Ketik min. 2 huruf untuk mencari…') : __('Klik atau ketik untuk mencari…')) }}"
                data-kosong="{{ __('Tidak ada yang cocok') }}"
                data-memuat="{{ __('Mencari…') }}">
            @if ($kosong !== null)
                <option value="">{{ $kosong }}</option>
            @endif
            @foreach ($tanpaKelompok as $o)
                {!! $opsiHtml($o) !!}
            @endforeach
            @foreach ($kelompok as $namaKelompok => $isi)
                <optgroup label="{{ $namaKelompok }}">
                    @foreach ($isi as $o)
                        {!! $opsiHtml($o) !!}
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>
    @error($model) <div class="invalid-feedback d-block" id="{{ $id }}-galat">{{ $message }}</div> @enderror
</div>
