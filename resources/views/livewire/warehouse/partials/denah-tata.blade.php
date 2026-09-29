{{--
    Mode Tata letak (A-365, A-369): panel "Barang belum punya tempat" — cari ke server (maks. 50),
    centang barang, "Taruh di…", lalu klik rak / bin / area di denah (atau ketuk di versi daftar).
    Semua baru tersimpan lewat "Simpan perubahan".
--}}
<div class="card mb-3 border-info" data-panel-belum-bertempat>
    <div class="card-header d-block">
        <div class="fw-semibold fs-6 text-body" style="text-transform: none; letter-spacing: normal">{{ __('Barang belum punya tempat') }}</div>
        <div class="small text-muted">{{ __('Barang aktif yang belum punya tempat simpan di gudang ini.') }}</div>
    </div>
    <div class="card-body small">
        <input class="form-control form-control-sm mb-2" type="search" x-model="belum.cari" x-on:input.debounce.300ms="cariBelum()"
               placeholder="{{ __('Cari kode atau nama barang…') }}" aria-label="{{ __('Cari barang belum punya tempat') }}" data-cari-belum>
        <div class="text-muted" x-show="belum.memuat">{{ __('Memuat…') }}</div>
        <div class="list-group list-group-flush border rounded overflow-auto" style="max-height: 16rem" x-show="!belum.memuat">
            <template x-for="b in belum.barang" :key="b.id">
                <label class="list-group-item list-group-item-action d-flex gap-2 py-1">
                    <input class="form-check-input mt-1" type="checkbox" :checked="dicentang(b)" x-on:change="centang(b)">
                    <span><strong x-text="b.code"></strong> <span class="text-muted" x-text="b.name"></span></span>
                </label>
            </template>
            <div class="list-group-item text-muted" x-show="!belum.barang.length">{{ __('Semua barang yang cocok sudah punya tempat.') }}</div>
        </div>
        <div class="text-muted mt-1" x-show="belum.lebih">{{ __('Menampilkan 50 barang pertama — persempit pencarian.') }}</div>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="khusus-taruh" x-model="khususTaruh">
                <label class="form-check-label" for="khusus-taruh" title="{{ __('Barang lain ditolak di tempat ini') }}">{{ __('Khusus barang ini') }}</label>
            </div>
            <button class="btn btn-sm btn-primary" type="button" x-on:click="mulaiTaruh()" :disabled="!barangPilih.length" data-taruh-di>
                <i class="bi bi-box-arrow-in-down"></i> {{ __('Taruh di…') }} (<span x-text="barangPilih.length"></span>)
            </button>
            <button class="btn btn-sm btn-outline-secondary" type="button" x-show="menaruh" x-on:click="menaruh = false">{{ __('Batal pilih tempat') }}</button>
        </div>
        <div class="alert alert-info py-1 px-2 mt-2 mb-0" x-show="menaruh" x-cloak>
            {{ __('Klik rak (= seluruh rak), petak bin, atau area lantai tujuan di denah.') }}
        </div>
    </div>
</div>
