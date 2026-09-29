{{--
    Tata letak gudang Bagian 2 (K-B–K-E, A-359–A-364) — tab Atur panel rak:
    pilih petak → Gabung (arah & sifat) / Pisah / Hapus bin (hanya bila belum pernah
    dipakai) atau Nonaktifkan / Lebar bin; area lantai → kapasitas bebas.
    Semua baru tersimpan setelah "Simpan perubahan". Variabel: $alasan.
--}}
<template x-if="pilih?.jenis === 'rak' && !rakLokal(pilih.id)?.r.is_area">
    <div data-atur-petak>
        <hr>
        <div class="fw-semibold mb-1">{{ __('Petak (bin)') }}</div>
        <div class="text-muted mb-1">{{ __('Ketuk petak untuk memilih. Pilih dua atau lebih untuk Gabung bin; pilih satu untuk Pisah, Lebar, Hapus, atau Nonaktifkan.') }}</div>
        <div class="d-grid gap-1">
            <template x-for="lv in (rakLokal(pilih.id)?.r.levels ?? [])" :key="'p' + lv.id">
                <div class="d-grid gap-1" :style="'grid-template-columns: 2.2rem repeat(' + Math.max(1, ...(rakLokal(pilih.id)?.r.levels ?? []).map((l) => l.bins.length)) + ', minmax(0, 1fr))'">
                    <div class="fw-semibold text-muted d-flex align-items-center justify-content-end pe-1" x-text="lv.code"></div>
                    <template x-for="b in lv.bins" :key="'p' + b.id">
                        <button type="button" class="btn btn-sm border py-0 px-1 text-truncate" :class="(dipilihPetak(b) || (b.utama && pilihPetak.includes(String(b.utama)))) && 'border-primary border-2'"
                                :style="'background:' + warnaBin(b)" :title="labelGabung(b, rakLokal(pilih.id)?.r) + ' · ' + b.code"
                                x-on:click="togglePetak(b)" :disabled="!idNyata(b.id)">
                            <span x-text="b.short"></span><span x-show="b.utama || (b.tergabung ?? []).length"> ⧉</span><span class="text-muted" x-show="b.lebar" x-text="' ' + angka(b.lebar) + 'm'"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>

        {{-- Gabung: dua petak atau lebih. --}}
        <div class="border rounded p-2 mt-2" x-show="pilihPetak.length > 1" data-form-gabung>
            <div class="fw-semibold mb-1">{{ __('Gabung bin') }}</div>
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label mb-0" for="gb-utama">{{ __('Bin utama (stok dicatat di sini)') }}</label>
                    <select class="form-select form-select-sm" id="gb-utama" x-model="f.gabung.utama">
                        <template x-for="b in petakTerpilih()" :key="'u' + b.id"><option :value="String(b.id)" x-text="b.pendek + (b.total > 0 ? ' · berisi' : '')"></option></template>
                    </select>
                </div>
                <div class="col-3">
                    <label class="form-label mb-0" for="gb-arah">{{ __('Arah') }}</label>
                    <select class="form-select form-select-sm" id="gb-arah" x-model="f.gabung.arah">
                        @foreach (\App\Domain\Warehouse\Enums\BinMergeDirection::options() as $k => $t) <option value="{{ $k }}">{{ $t }}</option> @endforeach
                    </select>
                </div>
                <div class="col-3">
                    <label class="form-label mb-0" for="gb-sifat">{{ __('Sifat') }}</label>
                    <select class="form-select form-select-sm" id="gb-sifat" x-model="f.gabung.sifat">
                        @foreach (\App\Domain\Warehouse\Enums\BinMergeType::options() as $k => $t) <option value="{{ $k }}">{{ $t }}</option> @endforeach
                    </select>
                </div>
                <div class="col-12"><label class="form-label mb-0" for="gb-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label><input class="form-control form-control-sm" id="gb-alasan" type="text" maxlength="255" x-model="f.gabung.alasan" placeholder="{{ __('mis. genset besar memakan 2 petak') }}"></div>
            </div>
            <div class="text-muted mt-1">{{ __('Samping = petak bersebelahan di tingkat yang sama; Atas = petak bernomor sama di tingkat atasnya. Petak lain wajib kosong. Sementara dipisah otomatis saat bin utama kosong.') }}</div>
            <button class="btn btn-sm btn-primary mt-2" type="button" x-on:click="gabungkan()">{{ __('Gabung') }}</button>
        </div>

        {{-- Satu petak: pisah, lebar, hapus/nonaktif. --}}
        <template x-if="pilihPetak.length === 1 && petakTerpilih()[0]">
            <div class="border rounded p-2 mt-2" data-form-petak>
                <div class="fw-semibold mb-1" x-text="labelGabung(petakTerpilih()[0], rakLokal(pilih.id)?.r)"></div>

                <div x-show="(petakTerpilih()[0].tergabung ?? []).length" class="mb-2">
                    <div class="text-muted" x-text="'Gabungan ' + ({ side: 'samping', above: 'atas' })[petakRak().find((x) => String(x.utama) === String(petakTerpilih()[0].id))?.arah] + ', ' + ({ temporary: 'sementara', permanent: 'permanen' })[petakRak().find((x) => String(x.utama) === String(petakTerpilih()[0].id))?.sifat]"></div>
                    <div class="d-flex gap-2 mt-1">
                        <input class="form-control form-control-sm" type="text" maxlength="255" x-model="f.petak.alasan" placeholder="{{ __('Alasan (wajib untuk permanen)') }}" aria-label="{{ __('Alasan pisah') }}">
                        <button class="btn btn-sm btn-outline-primary text-nowrap" type="button" x-on:click="pisahkan()">{{ __('Pisah') }}</button>
                    </div>
                </div>

                <div class="d-flex align-items-end gap-2">
                    <div><label class="form-label mb-0" for="pt-lebar">{{ __('Lebar bin (m)') }}</label><input class="form-control form-control-sm" id="pt-lebar" style="max-width: 7rem" type="text" inputmode="decimal" x-model="f.petak.lebar" placeholder="{{ __('rata bagi') }}"></div>
                    <button class="btn btn-sm btn-outline-primary" type="button" x-on:click="terapkanLebar()">{{ __('Terapkan lebar') }}</button>
                </div>

                <div class="mt-2" x-show="bisaHapus(petakTerpilih()[0])">
                    <button class="btn btn-sm btn-outline-danger" type="button" x-on:click="hapusBin()" data-hapus-bin><i class="bi bi-trash"></i> {{ __('Hapus bin') }}</button>
                    <span class="text-muted">{{ __('Belum pernah dipakai — boleh dihapus.') }}</span>
                </div>
                <div class="mt-2" x-show="!bisaHapus(petakTerpilih()[0]) && !petakTerpilih()[0].nonaktif">
                    <div class="d-flex flex-wrap gap-2">
                        <select class="form-select form-select-sm w-auto" x-model="f.alasan" aria-label="{{ __('Alasan nonaktif') }}">
                            <option value="">{{ __('Alasan…') }}</option>
                            @foreach ($alasan as $kode => $teks) <option value="{{ $kode }}">{{ $teks }}</option> @endforeach
                        </select>
                        <button class="btn btn-sm btn-outline-danger" type="button" x-on:click="nonaktifBin()">{{ __('Nonaktifkan bin') }}</button>
                    </div>
                    <div class="text-muted mt-1">{{ __('Bin yang pernah dipakai tidak dihapus, hanya dinonaktifkan (harus kosong & tidak dalam gabungan).') }}</div>
                </div>
            </div>
        </template>
    </div>
</template>

{{-- K-E: area lantai berkapasitas bebas. --}}
<template x-if="pilih?.jenis === 'rak' && rakLokal(pilih.id)?.r.is_area">
    <div data-kapasitas-area>
        <hr>
        <div class="fw-semibold mb-1">{{ __('Kapasitas area lantai') }}</div>
        <div class="row g-2">
            <div class="col-4"><label class="form-label mb-0" for="ka-qty">{{ __('Jumlah (unit)') }}</label><input class="form-control form-control-sm" id="ka-qty" type="text" inputmode="decimal" x-model="f.kapasitas.capacity_qty" placeholder="{{ __('tanpa batas') }}"></div>
            <div class="col-4"><label class="form-label mb-0" for="ka-berat">{{ __('Berat (kg)') }}</label><input class="form-control form-control-sm" id="ka-berat" type="text" inputmode="decimal" x-model="f.kapasitas.capacity_weight" placeholder="{{ __('tanpa batas') }}"></div>
            <div class="col-4"><label class="form-label mb-0" for="ka-vol">{{ __('Volume (m³)') }}</label><input class="form-control form-control-sm" id="ka-vol" type="text" inputmode="decimal" x-model="f.kapasitas.capacity_volume" placeholder="{{ __('tanpa batas') }}"></div>
        </div>
        <div class="text-muted mt-1">{{ __('Kosong = tanpa batas. Jumlah dihitung dari seluruh isi area dan ditolak bila terlampaui.') }}</div>
        <button class="btn btn-sm btn-outline-primary mt-2" type="button" x-on:click="terapkanKapasitasArea()">{{ __('Terapkan kapasitas') }}</button>
    </div>
</template>
