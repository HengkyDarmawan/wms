{{--
    Mode Atur denah (A-353): "Tambah" selalu terbuka (zona, rak, area lantai); tingkat, bin,
    putar, dan nonaktif ada di panel rak. Pengaturan jarang dipakai dilipat di "Lanjutan"
    (K-K): ukuran gedung, objek denah, peringatan tumpukan. Semua baru tersimpan setelah
    "Simpan perubahan".
--}}
<div class="card mb-3" id="tambah-struktur">
    <div class="card-header"><strong>{{ __('Tambah') }}</strong> <span class="small text-muted">{{ __('kode tidak bisa diubah setelah disimpan karena membentuk kode bin') }}</span></div>
    <div class="card-body small">
        <div class="fw-semibold mb-1">{{ __('Zona baru') }}</div>
        <div class="row g-2 mb-3">
            <div class="col-md-3"><label class="form-label mb-0" for="zb-kode">{{ __('Kode zona') }} <span class="wajib">*</span></label><input class="form-control form-control-sm" id="zb-kode" type="text" maxlength="10" x-model="f.zona.code" placeholder="mis. C"></div>
            <div class="col-md-5"><label class="form-label mb-0" for="zb-nama">{{ __('Nama zona') }} <span class="wajib">*</span></label><input class="form-control form-control-sm" id="zb-nama" type="text" maxlength="60" x-model="f.zona.name" placeholder="{{ __('mis. Zona besi') }}"></div>
            <div class="col-md-4 d-flex align-items-end"><button class="btn btn-sm btn-primary" type="button" x-on:click="tambahZona()">{{ __('Tambah zona') }}</button></div>
        </div>

        <div class="fw-semibold mb-1">{{ __('Rak baru') }}</div>
        <div class="row g-2">
            <div class="col-md-2">
                <label class="form-label mb-0" for="rb-zona">{{ __('Zona') }} <span class="wajib">*</span></label>
                <select class="form-select form-select-sm" id="rb-zona" x-model="f.rak.zona">
                    <option value="">—</option>
                    <template x-for="z in d.zones" :key="z.id"><option :value="z.id" x-text="z.code"></option></template>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label mb-0" for="rb-kode">{{ __('Kode rak') }} <span class="wajib">*</span></label><input class="form-control form-control-sm" id="rb-kode" type="text" maxlength="10" x-model="f.rak.code" placeholder="mis. R05"></div>
            <div class="col-md-3"><label class="form-label mb-0" for="rb-nama">{{ __('Nama rak') }}</label><input class="form-control form-control-sm" id="rb-nama" type="text" maxlength="60" x-model="f.rak.name" placeholder="{{ __('opsional') }}"></div>
            <div class="col-md-1"><label class="form-label mb-0" for="rb-level">{{ __('Tingkat') }}</label><input class="form-control form-control-sm" id="rb-level" type="number" min="1" :max="meta.maksLevel" x-model="f.rak.levels"></div>
            <div class="col-md-2"><label class="form-label mb-0" for="rb-bin">{{ __('Bin per tingkat') }}</label><input class="form-control form-control-sm" id="rb-bin" type="number" min="0" :max="meta.maksBinPerLevel" x-model="f.rak.bins"></div>
            <div class="col-md-2"><label class="form-label mb-0" for="rb-kap">{{ __('Kapasitas bin') }}</label><input class="form-control form-control-sm" id="rb-kap" type="number" min="0" step="any" x-model="f.rak.capacity_qty" placeholder="{{ __('opsional') }}"></div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <button class="btn btn-sm btn-primary" type="button" x-on:click="tambahRak()">{{ __('Tambah rak') }}</button>
            <span class="text-muted">{{ __('Tingkat diberi kode L1, L2, …; bin B01, B02, … per tingkat. Rak baru ditata otomatis — seret untuk memindahkan.') }}</span>
        </div>

        <hr>
        <div class="fw-semibold mb-1">{{ __('Area lantai (barang besar tanpa rak, mis. alat berat)') }}</div>
        <div class="row g-2">
            <div class="col-md-2">
                <label class="form-label mb-0" for="area-zona">{{ __('Zona') }}</label>
                <select class="form-select form-select-sm" id="area-zona" x-model="f.area.zona">
                    <option value="">—</option>
                    <template x-for="z in d.zones" :key="z.id"><option :value="z.id" x-text="z.code"></option></template>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label mb-0" for="area-kode">{{ __('Kode') }}</label><input class="form-control form-control-sm" id="area-kode" type="text" maxlength="10" x-model="f.area.code" placeholder="mis. AB1"></div>
            <div class="col-md-3"><label class="form-label mb-0" for="area-nama">{{ __('Nama') }}</label><input class="form-control form-control-sm" id="area-nama" type="text" maxlength="60" x-model="f.area.name" placeholder="{{ __('mis. Parkir excavator') }}"></div>
            <div class="col-md-2"><label class="form-label mb-0" for="area-kap">{{ __('Kapasitas (unit)') }}</label><input class="form-control form-control-sm" id="area-kap" type="number" min="1" step="1" x-model="f.area.capacity_qty"></div>
            <div class="col-md-3 d-flex align-items-end"><label class="form-check mb-1"><input class="form-check-input" type="checkbox" x-model="f.area.seluruh_zona"> <span class="form-check-label">{{ __('Seluruh zona') }}</span></label></div>
        </div>
        <button class="btn btn-sm btn-primary mt-2" type="button" x-on:click="tambahArea()">{{ __('Tambah area lantai') }}</button>
    </div>
</div>

{{-- K-K: pengaturan lanjutan dilipat. T-07: objek denah berupa tombol biasa (bukan menu tarik-turun yang terpotong). --}}
<div class="card mb-3" data-lanjutan>
    <button class="card-header btn btn-link text-start text-decoration-none w-100 d-flex align-items-center gap-2" type="button" x-on:click="lanjutan = !lanjutan" :aria-expanded="lanjutan">
        <i class="bi" :class="lanjutan ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
        <strong>{{ __('Lanjutan') }}</strong>
        <span class="small text-muted">{{ __('ukuran gedung, objek denah, peringatan tumpukan') }}</span>
        <span class="badge text-bg-warning ms-auto" x-show="tumpukan().pesan.length" x-text="tumpukan().pesan.length + ' tumpukan'"></span>
    </button>
    <div class="card-body small" x-show="lanjutan" x-cloak>
        <div class="fw-semibold mb-1">{{ __('Ukuran gedung') }}</div>
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div><label class="form-label mb-0" for="gedung-p">{{ __('Panjang gedung (m)') }}</label><input class="form-control form-control-sm" id="gedung-p" type="text" inputmode="decimal" x-model="f.gedung.length_m" placeholder="{{ __('mis. 40') }}"></div>
            <div><label class="form-label mb-0" for="gedung-l">{{ __('Lebar gedung (m)') }}</label><input class="form-control form-control-sm" id="gedung-l" type="text" inputmode="decimal" x-model="f.gedung.width_m" placeholder="{{ __('mis. 24') }}"></div>
            <button class="btn btn-sm btn-outline-primary" type="button" x-on:click="simpanGedung()">{{ __('Terapkan ukuran gedung') }}</button>
            <span class="text-muted">{{ __('Kosongkan keduanya bila denah cukup digambar dari zona.') }}</span>
        </div>

        <hr>
        <div class="fw-semibold mb-1">{{ __('Objek denah (tanpa stok)') }}</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($jenisObjek as $nilai => $label)
                <button class="btn btn-sm btn-outline-secondary" type="button" x-on:click="tambahObjek(@js($nilai))" data-objek-baru="{{ $nilai }}"><i class="bi bi-plus-lg"></i> {{ $label }}</button>
            @endforeach
        </div>

        <hr>
        <div class="fw-semibold mb-1">{{ __('Peringatan tumpukan') }}</div>
        <div class="text-muted" x-show="!tumpukan().pesan.length">{{ __('Tidak ada benda yang bertumpuk.') }}</div>
        <div class="alert alert-warning py-2 mb-0" role="status" x-show="tumpukan().pesan.length" data-tumpukan>
            <i class="bi bi-exclamation-triangle"></i> <span x-text="tumpukan().pesan.join('; ')"></span>.
            <span class="text-muted">{{ __('Geser salah satunya; peringatan ini tidak menolak simpan.') }}</span>
        </div>
    </div>
</div>
