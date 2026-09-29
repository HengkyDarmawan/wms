{{--
    "Barang di sini" (A-365) di panel rak: tempat simpan seluruh rak / area dan bin terpilih.
    Mode Lihat hanya menampilkan; mode Tata letak bisa Lepas dan "Tambah barang…".
--}}
<div class="mt-2" data-barang-di-sini>
    <div class="fw-semibold">{{ __('Barang di sini') }}</div>
    <template x-for="x in barangDiSini()" :key="x.tempat + ':' + x.id">
        <div class="d-flex align-items-center gap-2 border-bottom py-1">
            <span class="me-auto"><strong x-text="x.code"></strong> <span class="text-muted" x-text="namaBarang[x.id] ?? ''"></span>
                <span class="text-muted" x-text="'· ' + x.dimana"></span>
                <span class="badge text-bg-warning" x-show="x.k">{{ __('Khusus') }}</span></span>
            <button class="btn btn-sm btn-outline-danger py-0" type="button" x-show="tata" x-on:click="lepasBarang(x)">{{ __('Lepas') }}</button>
        </div>
    </template>
    <div class="text-muted" x-show="!barangDiSini().length">{{ __('Belum ada barang yang ditetapkan di sini.') }}</div>

    @if ($bolehUbah)
        <div class="border rounded p-2 mt-2" x-show="tata" x-cloak data-tambah-barang>
            <div class="fw-semibold mb-1">{{ __('Tambah barang…') }}</div>
            <div class="d-flex flex-wrap gap-3 mb-1" x-show="!rak?.is_area">
                <label class="form-check mb-0"><input class="form-check-input" type="radio" value="bin" x-model="tambahBrg.ke"> <span x-text="'Bin ' + (binTerpilih()?.pendek ?? '—')"></span></label>
                <label class="form-check mb-0"><input class="form-check-input" type="radio" value="rak" x-model="tambahBrg.ke"> {{ __('Seluruh rak') }}</label>
            </div>
            <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" id="khusus-tambah" x-model="tambahBrg.khusus">
                <label class="form-check-label" for="khusus-tambah">{{ __('Khusus barang ini') }}</label>
            </div>
            <input class="form-control form-control-sm" type="search" x-model="tambahBrg.cari" x-on:input.debounce.300ms="cariTambah()"
                   placeholder="{{ __('Cari kode atau nama barang…') }}" aria-label="{{ __('Cari barang untuk ditambah') }}">
            <div class="list-group list-group-flush overflow-auto mt-1" style="max-height: 12rem">
                <template x-for="b in tambahBrg.barang" :key="b.id">
                    <button class="list-group-item list-group-item-action py-1 text-start" type="button" x-on:click="tambahKeRak(b)">
                        <strong x-text="b.code"></strong> <span class="text-muted" x-text="b.name"></span>
                    </button>
                </template>
            </div>
            <div class="text-muted" x-show="tambahBrg.lebih">{{ __('Menampilkan 50 barang pertama — persempit pencarian.') }}</div>
        </div>
    @endif
</div>
