{{--
    Pemandu di layar (A-414) — tombol "Tunjukkan caranya" + langkah tur (driver.js, resources/js/wms/tur.js).
    Pemakaian: <x-tur kunci="item-baru" :langkah="[['el' => '#item-kode', 'judul' => 'Kode item', 'isi' => '…'], …]" />
    `el` boleh kosong → penjelasan tampil di tengah layar.
--}}
@props(['kunci', 'langkah' => [], 'label' => null])
@php($idTur = 'tur-'.$kunci)
<button type="button" {{ $attributes->merge(['class' => 'btn btn-outline-info']) }} data-tur-mulai="{{ $idTur }}" data-tur="{{ $kunci }}">
    <i class="bi bi-signpost-2"></i> {{ $label ?? __('Tunjukkan caranya') }}
</button>
<script type="application/json" id="{{ $idTur }}">@json(array_values($langkah))</script>
