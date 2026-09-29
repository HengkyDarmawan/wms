{{--
    Denah ringan (K-H, A-353): halaman dirender sekali; gambar, pilih, cari, geser, ubah,
    dan urungkan dikerjakan Alpine `denahGedung` di browser (resources/js/wms/floor-plan.js).
    Server hanya dipanggil untuk isi rak (klik) dan "Simpan perubahan".
--}}
@php
    $labelStatus = ['kosong' => __('Kosong'), 'terisi' => __('Terisi'), 'penuh' => __('Penuh'), 'beku' => __('Dibekukan (opname)'), 'terpakai' => __('Bin tergabung / area terisi')];
    $warnaStatus = ['kosong' => '#f1f3f5', 'terisi' => '#b2f2bb', 'penuh' => '#ffc9c9', 'beku' => '#a5d8ff', 'terpakai' => '#d0bfff'];
@endphp
<div>
    {{-- Data denah sekali muat: JSON di tag script (lebih ringkas daripada atribut x-data). --}}
    <script type="application/json" id="denah-data-{{ $this->getId() }}">{!! json_encode($awal, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <div wire:ignore x-data="denahGedung('denah-data-{{ $this->getId() }}')" x-on:keydown.window="tombol($event)" data-denah>
        @unless ($ringkas)
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h1 class="h3 mb-1">{{ __('Denah gudang') }} {{ $gudang->code }}</h1>
                    <p class="text-muted mb-0">{{ $gudang->name }} · {{ __('tampak atas; klik rak untuk melihat isinya.') }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if ($bolehUbah)
                        <button class="btn btn-outline-warning d-none d-md-inline-block" type="button" x-show="!edit" x-on:click="aturEdit(true)">
                            <i class="bi bi-pencil-square"></i> {{ __('Atur denah') }}
                        </button>
                        <button class="btn btn-warning" type="button" x-show="edit" x-cloak x-on:click="aturEdit(false)">
                            <i class="bi bi-check2-square"></i> {{ __('Selesai mengatur') }}
                        </button>
                    @endif
                    <a class="btn btn-outline-secondary" href="{{ route('warehouses.show', $gudang) }}">{{ __('Kembali ke gudang') }}</a>
                </div>
            </div>
        @endunless

        {{-- Bilah alat: warna, cari, zoom, tampilan; di mode Atur: urungkan, putar, simpan. --}}
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Warna denah') }}">
                    <button class="btn" type="button" :class="warna === 'status' ? 'btn-primary' : 'btn-outline-primary'" x-on:click="warna = 'status'; gambar()">{{ __('Warna: status') }}</button>
                    <button class="btn" type="button" :class="warna === 'umur' ? 'btn-primary' : 'btn-outline-primary'" x-on:click="warna = 'umur'; gambar()">{{ __('Warna: umur stok') }}</button>
                </div>
                <input class="form-control form-control-sm" style="max-width: 16rem" type="search" x-model="cari" x-on:input.debounce.150ms="gambar()"
                       placeholder="{{ __('Cari bin, item, lot, serial…') }}" aria-label="{{ __('Cari di denah') }}" data-cari-denah>
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Perbesaran') }}" x-show="!tampilDaftar">
                    <button class="btn btn-outline-secondary" type="button" x-on:click="perkecil()" title="{{ __('Perkecil') }}" aria-label="{{ __('Perkecil') }}">−</button>
                    <button class="btn btn-outline-secondary" type="button" x-on:click="pas(true)" data-pas-layar>{{ __('Pas layar') }}</button>
                    <button class="btn btn-outline-secondary" type="button" x-on:click="perbesar()" title="{{ __('Perbesar') }}" aria-label="{{ __('Perbesar') }}">+</button>
                </div>
                <button class="btn btn-sm btn-outline-secondary" type="button" x-on:click="tampilDaftar = !tampilDaftar; $nextTick(() => { gambar(); pas(true) })" data-ganti-tampilan>
                    <i class="bi" :class="tampilDaftar ? 'bi-map' : 'bi-list-ul'"></i> <span x-text="tampilDaftar ? @js(__('Gambar')) : @js(__('Daftar'))"></span>
                </button>

                @if ($bolehUbah)
                    <div class="ms-auto d-flex flex-wrap align-items-center gap-2" x-show="edit" x-cloak>
                        <button class="btn btn-sm btn-outline-secondary" type="button" x-on:click="urungkan()" :disabled="!riwayat.length" title="Ctrl+Z" data-urungkan>
                            <i class="bi bi-arrow-counterclockwise"></i> {{ __('Urungkan') }}
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" type="button" x-on:click="putar()" :disabled="!pilih || !['rak', 'obj'].includes(pilih.jenis)" title="R" data-putar>
                            <i class="bi bi-arrow-clockwise"></i> {{ __('Putar 90°') }}
                        </button>
                        <button class="btn btn-sm btn-primary" type="button" x-on:click="simpan()" :disabled="!ops.length || menyimpan" data-simpan-denah>
                            <span class="spinner-border spinner-border-sm" x-show="menyimpan" x-cloak></span>
                            <i class="bi bi-save" x-show="!menyimpan"></i> {{ __('Simpan perubahan') }} (<span x-text="ops.length"></span>)
                        </button>
                    </div>
                @endif
            </div>

            <div class="card-footer small" x-show="q !== ''" x-cloak>
                <template x-for="h in hasilCari()" :key="h.bin">
                    <button class="btn btn-sm btn-outline-secondary py-0 me-1 mb-1" type="button" x-on:click="bukaRak(h.rak, h.bin)" :title="h.code" x-text="h.pendek"></button>
                </template>
                <span class="text-muted" x-show="!hasilCari().length">{{ __('Tidak ada bin yang cocok.') }}</span>
            </div>
        </div>

        <div class="alert alert-danger small" role="alert" x-show="galat.length" x-cloak data-galat-denah>
            <strong>{{ __('Belum tersimpan.') }}</strong> {{ __('Perubahan berikut ditolak; semua perubahan dibatalkan. Perbaiki atau urungkan, lalu simpan lagi.') }}
            <ul class="mb-0 mt-1">
                <template x-for="g in galat" :key="g.i"><li x-text="g.pesan"></li></template>
            </ul>
        </div>

        <div class="row g-3">
            <div :class="(rak || pilih) ? 'col-xl-8' : 'col-12'">
                <div class="card mb-3" x-show="!tampilDaftar">
                    <div class="card-body p-2">
                        <div class="overflow-auto" x-ref="wrap" style="max-height: 70vh">
                            <svg x-ref="svg" role="img" aria-label="{{ __('Denah gedung') }} {{ $gudang->code }}" data-denah-gedung
                                 style="touch-action: none; user-select: none"
                                 x-on:pointerdown="mulai($event)" x-on:pointermove="gerak($event)" x-on:pointerup="lepas($event)"></svg>
                        </div>
                    </div>
                    <div class="card-footer d-flex flex-wrap justify-content-between gap-2 small">
                        <div class="d-flex flex-wrap gap-2" x-show="warna === 'status'">
                            @foreach ($warnaStatus as $k => $w)
                                <span><span class="d-inline-block border rounded-1 align-middle" style="width: 1rem; height: 1rem; background: {{ $w }}"></span> {{ $labelStatus[$k] }}</span>
                            @endforeach
                        </div>
                        <div class="d-flex flex-wrap gap-2" x-show="warna === 'umur'" x-cloak>
                            @foreach ([['#b2f2bb', '< 30 '.__('hari')], ['#ffec99', '30–89'], ['#ffd8a8', '90–179'], ['#ffc9c9', '≥ 180']] as [$w, $t])
                                <span><span class="d-inline-block border rounded-1 align-middle" style="width: 1rem; height: 1rem; background: {{ $w }}"></span> {{ $t }}</span>
                            @endforeach
                        </div>
                        <span class="text-muted" x-show="edit" x-cloak>{{ __('Seret untuk memindah · tarik kotak kecil di sudut untuk ukuran · panah geser 0,5 m (Shift 0,1 m) · R putar · Ctrl+Z urungkan') }}</span>
                    </div>
                </div>

                <div x-show="tampilDaftar" x-cloak>
                    @include('livewire.warehouse.partials.denah-daftar')
                </div>

                <div class="alert alert-info" x-show="!d.zones.length" x-cloak>
                    {{ $bolehUbah ? __('Gudang ini belum punya zona. Klik Atur denah lalu Tambah zona.') : __('Gudang ini belum punya zona.') }}
                </div>

                @if ($bolehUbah)
                    <div x-show="edit" x-cloak>
                        @include('livewire.warehouse.partials.denah-atur')
                    </div>
                @endif
            </div>

            <div class="col-xl-4" x-show="rak || pilih" x-cloak>
                @include('livewire.warehouse.partials.denah-panel')
            </div>
        </div>
    </div>
</div>
