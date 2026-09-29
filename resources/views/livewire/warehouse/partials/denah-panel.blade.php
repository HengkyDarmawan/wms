{{--
    Panel samping Denah (Alpine, A-353): rak (Isi | Atur), zona, atau objek denah.
    Isi rak diambil sekali saat rak diklik ($wire.isiRak); isian Atur hanya mengubah
    data di browser sampai "Simpan perubahan". Variabel: $bolehUbah, $jenisObjek, $alasan.
--}}
{{-- Rak --}}
<div class="card mb-3 border-primary" x-show="rak" x-cloak data-panel-rak>
    <div class="card-header d-block">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
                <div class="fw-semibold fs-6 text-body" style="text-transform: none; letter-spacing: normal">
                    <span x-text="'Rak ' + (rak?.code ?? '') + ' · Zona ' + (rak?.zona ?? '') + ' — ' + (rak?.zona_nama ?? '')"></span>
                    <span class="badge text-bg-secondary" x-show="rak?.is_area">{{ __('area lantai') }}</span>
                    <span class="badge text-bg-warning" x-show="rak?.baru">{{ __('belum disimpan') }}</span>
                </div>
                <div class="small text-muted" x-show="rak?.name" x-text="'Nama rak: ' + (rak?.name ?? '')"></div>
                <div class="small text-muted" x-show="rak?.is_area" x-text="'Kapasitas' + teksKapasitasArea(rakLokal(rak?.id)?.r ?? rak ?? {})" data-kapasitas-area-isi></div>
            </div>
            <button class="btn-close" type="button" x-on:click="tutup()" aria-label="{{ __('Tutup') }}"></button>
        </div>
        <ul class="nav nav-tabs card-header-tabs mt-2" x-show="edit">
            <li class="nav-item"><button class="nav-link" :class="tab === 'isi' && 'active'" type="button" x-on:click="tab = 'isi'">{{ __('Isi') }}</button></li>
            <li class="nav-item"><button class="nav-link" :class="tab === 'atur' && 'active'" type="button" x-on:click="tab = 'atur'" data-tab-atur-rak>{{ __('Atur') }}</button></li>
        </ul>
    </div>

    <div class="card-body small" x-show="!edit || tab === 'isi'">
        <div class="text-muted" x-show="memuat">{{ __('Memuat isi rak…') }}</div>
        <div x-show="!memuat">
            <div class="text-muted mb-1">{{ __('Tampak depan (tingkat paling bawah di bawah) — ketuk petak') }}</div>
            <div class="d-grid gap-1" data-tampak-depan>
                <template x-for="lv in (rak?.levels ?? [])" :key="lv.id">
                    <div class="d-grid gap-1 align-items-stretch" :style="'grid-template-columns: 2.2rem repeat(' + kolomRak() + ', minmax(0, 1fr))'">
                        <div class="fw-semibold text-muted d-flex align-items-center justify-content-end pe-1" x-text="lv.code"></div>
                        <template x-for="b in lv.bins" :key="b.id">
                            <button type="button" class="btn btn-sm text-start border" :class="binId === b.id && 'border-primary border-2'"
                                    :style="'line-height: 1.15; background:' + warnaPetak(b)" :data-bin="b.code" x-on:click="binId = b.utama || b.id; gambar()">
                                <span class="fw-semibold" x-text="b.pendek"></span><br>
                                {{-- A-359: bin tergabung tidak menyimpan stok; ketuk = bin utamanya. --}}
                                <span class="text-muted" x-text="b.utama ? '⧉ ikut ' + String(b.utama_kode ?? '').split('-').pop() : ringkasBin(b)"></span>
                            </button>
                        </template>
                    </div>
                </template>
                <div class="rounded" style="height: 6px; background: #868e96; margin-left: 2.4rem"></div>
            </div>

            <hr>
            <template x-if="binTerpilih()">
                <div data-isi-bin x-data="{ salin: false }">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold fs-6" x-text="binTerpilih().pendek"></div>
                            <div class="font-monospace text-muted" style="font-size: .75rem">
                                <span x-text="binTerpilih().code"></span>
                                <button class="btn btn-link btn-sm p-0 ms-1 align-baseline" type="button" title="{{ __('Salin kode lengkap') }}" aria-label="{{ __('Salin kode lengkap') }}"
                                        x-on:click="navigator.clipboard?.writeText(binTerpilih().code); salin = true; setTimeout(() => salin = false, 1500)">
                                    <i class="bi" :class="salin ? 'bi-clipboard-check' : 'bi-clipboard'"></i>
                                </button>
                            </div>
                        </div>
                        <div class="text-end" x-show="binTerpilih().capacity_qty !== null">
                            <span class="text-muted" x-text="angka(binTerpilih().total) + ' / ' + angka(binTerpilih().capacity_qty)"></span>
                        </div>
                    </div>
                    <span class="badge text-bg-secondary mt-1" x-show="binTerpilih().nonaktif">{{ __('nonaktif') }}</span>
                    <span class="badge text-bg-info mt-1" x-show="binTerpilih().beku">{{ __('dibekukan opname') }}</span>
                    <div class="mt-1" x-show="(binTerpilih().tergabung ?? []).length" data-gabung-panel>
                        <span class="badge" style="background: #d0bfff; color: #212529" x-text="'Gabungan: ' + labelGabung(binTerpilih(), rakLokal(rak?.id)?.r)"></span>
                        <div class="text-muted small">{{ __('Stok gabungan dicatat di bin utama ini; kapasitas = jumlah kapasitas semua petaknya.') }}</div>
                    </div>
                    <div class="mt-1" x-show="binTerpilih().utama">
                        <span class="badge" style="background: #d0bfff; color: #212529" x-text="'Tergabung ke bin utama ' + (binTerpilih().utama_kode ?? '')"></span>
                        <div class="text-muted" x-text="binTerpilih().alasan_gabung ?? ''"></div>
                    </div>
                    <template x-for="(s, i) in (binTerpilih().isi ?? [])" :key="i">
                        <div class="border rounded p-2 mt-2">
                            <div class="d-flex justify-content-between gap-2">
                                <strong x-text="s.item_code"></strong>
                                <span><span x-text="angka(s.qty) + ' ' + (s.uom ?? '')"></span> <span class="text-muted" x-show="s.kemasan" x-text="'(' + s.kemasan + ')'"></span></span>
                            </div>
                            <div class="text-muted"><span x-text="s.item_name"></span><span x-show="s.tracking" x-text="' · ' + s.tracking"></span>
                                <span class="badge text-bg-light border" x-show="s.status && s.status !== 'Tersedia'" x-text="s.status"></span></div>
                            <div><span x-text="'Masuk ' + (s.masuk ?? '—') + ' · ' + s.umur + ' hari'"></span>
                                <span class="badge text-bg-warning" x-show="s.tertua" title="{{ __('Masuk paling lama untuk item ini di gudang — ambil dulu (FIFO)') }}">{{ __('tertua — ambil dulu') }}</span></div>
                        </div>
                    </template>
                    <div class="text-muted mt-2" x-show="!(binTerpilih().isi ?? []).length && !binTerpilih().utama">{{ __('Bin kosong.') }}</div>
                    <div class="mt-2"><a :href="@js(route('bins.index')) + '?q=' + encodeURIComponent(binTerpilih().code)">{{ __('Lihat di daftar bin') }}</a></div>
                </div>
            </template>
            <div class="text-muted" x-show="rak && !binTerpilih()">{{ __('Rak ini belum punya bin, atau belum disimpan.') }}</div>
        </div>
    </div>

    @if ($bolehUbah)
        <div class="card-body small" x-show="edit && tab === 'atur'" x-cloak data-tab-atur>
            <div class="text-muted mb-2">{{ __('Kode zona/rak/tingkat/bin terkunci setelah dibuat (BR-WH-01). Perubahan di sini baru tersimpan setelah "Simpan perubahan".') }}</div>
            <div class="row g-2">
                <div class="col-7"><label class="form-label mb-0" for="rak-nama">{{ __('Nama rak') }}</label><input class="form-control form-control-sm" id="rak-nama" type="text" maxlength="60" x-model="f.edit.name"></div>
                <div class="col-5">
                    <label class="form-label mb-0" for="rak-arah">{{ __('Arah') }}</label>
                    <select class="form-select form-select-sm" id="rak-arah" x-model="f.edit.orientation">
                        <option value="h">{{ __('Memanjang ke samping') }}</option>
                        <option value="v">{{ __('Memanjang ke bawah') }}</option>
                    </select>
                </div>
            </div>
            {{-- K-K: ukuran & posisi dilipat. --}}
            <details class="mt-2">
                <summary class="text-muted">{{ __('Lanjutan: ukuran & posisi') }}</summary>
                <div class="row g-2 mt-1">
                    @foreach (['length_m' => __('Panjang (m)'), 'width_m' => __('Lebar (m)'), 'height_m' => __('Tinggi (m)'), 'pos_x' => __('X (m)'), 'pos_y' => __('Y (m)')] as $k => $t)
                        <div class="col-4"><label class="form-label mb-0" for="rak-{{ $k }}">{{ $t }}</label><input class="form-control form-control-sm" id="rak-{{ $k }}" type="number" step="0.5" min="0" x-model="f.edit.{{ $k }}" placeholder="{{ __('opsional') }}"></div>
                    @endforeach
                </div>
            </details>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <button class="btn btn-sm btn-primary" type="button" x-on:click="terapkanIsian()">{{ __('Terapkan') }}</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" x-on:click="putar()"><i class="bi bi-arrow-clockwise"></i> {{ __('Putar 90°') }}</button>
            </div>
            <div class="text-muted mt-2">{{ __('Rak tidak bisa pindah zona karena kode binnya ikut berubah. Buat rak baru di zona tujuan, kosongkan rak ini, lalu nonaktifkan.') }}</div>

            <template x-if="pilih?.jenis === 'rak' && !rakLokal(pilih.id)?.r.is_area">
                <div>
                    <hr>
                    <div class="fw-semibold mb-1">{{ __('Tambah tingkat') }}</div>
                    <div class="row g-2">
                        <div class="col-5"><input class="form-control form-control-sm" type="text" maxlength="10" x-model="f.level.code" placeholder="{{ __('Kode (kosong = otomatis)') }}" aria-label="{{ __('Kode tingkat') }}"></div>
                        <div class="col-3"><input class="form-control form-control-sm" type="number" min="0" :max="meta.maksBinPerLevel" x-model="f.level.bins" aria-label="{{ __('Jumlah bin di tingkat baru') }}"></div>
                        <div class="col-4"><button class="btn btn-sm btn-outline-primary w-100" type="button" x-on:click="tambahLevel()">{{ __('Tambah tingkat') }}</button></div>
                    </div>
                    <div class="fw-semibold mt-3 mb-1">{{ __('Tambah bin') }}</div>
                    <template x-for="lv in (rakLokal(pilih.id)?.r.levels ?? [])" :key="lv.id">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="text-muted" style="width: 2.2rem" x-text="lv.code"></span>
                            <input class="form-control form-control-sm" style="max-width: 5rem" type="number" min="1" :max="meta.maksBinPerLevel" x-model="f.binBaru[lv.id]" placeholder="1" :aria-label="'Jumlah bin baru di tingkat ' + lv.code">
                            <button class="btn btn-sm btn-outline-primary" type="button" x-on:click="tambahBin(lv.id)">{{ __('Tambah bin') }}</button>
                        </div>
                    </template>
                </div>
            </template>

            @include('livewire.warehouse.partials.denah-atur-petak')

            @include('livewire.warehouse.partials.denah-nonaktif', ['label' => __('Nonaktifkan rak'), 'catatan' => __('Hanya bila semua bin kosong, tanpa reservasi, dan tidak dibekukan opname. Rak nonaktif tidak digambar.')])
        </div>
    @endif
</div>

{{-- Zona & objek (mode Atur) --}}
@if ($bolehUbah)
    <div class="card mb-3 border-primary" x-show="edit && pilih?.jenis === 'zona'" x-cloak data-panel-zona>
        <div class="card-header d-flex justify-content-between align-items-start">
            <div class="fw-semibold fs-6 text-body" style="text-transform: none; letter-spacing: normal" x-text="'Zona ' + (zona(pilih?.id)?.code ?? '') + ' — ' + (zona(pilih?.id)?.name ?? '')"></div>
            <button class="btn-close" type="button" x-on:click="tutup()" aria-label="{{ __('Tutup') }}"></button>
        </div>
        <div class="card-body small">
            <div class="text-muted mb-2">{{ __('Kode zona terkunci (BR-WH-01).') }}</div>
            <div class="row g-2">
                <div class="col-12"><label class="form-label mb-0" for="zona-nama">{{ __('Nama zona') }}</label><input class="form-control form-control-sm" id="zona-nama" type="text" maxlength="60" x-model="f.edit.name"></div>
                @foreach (['length_m' => __('Panjang (m)'), 'width_m' => __('Lebar (m)'), 'pos_x' => __('X di gedung (m)'), 'pos_y' => __('Y di gedung (m)')] as $k => $t)
                    <div class="col-6"><label class="form-label mb-0" for="zona-{{ $k }}">{{ $t }}</label><input class="form-control form-control-sm" id="zona-{{ $k }}" type="number" step="0.5" min="0" x-model="f.edit.{{ $k }}" placeholder="{{ __('opsional') }}"></div>
                @endforeach
            </div>
            <button class="btn btn-sm btn-primary mt-2" type="button" x-on:click="terapkanIsian()">{{ __('Terapkan') }}</button>
            @include('livewire.warehouse.partials.denah-nonaktif', ['label' => __('Nonaktifkan zona'), 'catatan' => __('Hanya bila semua raknya sudah nonaktif.')])
        </div>
    </div>

    <div class="card mb-3 border-primary" x-show="edit && pilih?.jenis === 'obj'" x-cloak data-panel-objek>
        <div class="card-header d-flex justify-content-between align-items-start">
            <div>
                <div class="fw-semibold fs-6 text-body" style="text-transform: none; letter-spacing: normal" x-text="objek(pilih?.id)?.name ?? ''"></div>
                <div class="small text-muted"><span x-text="objek(pilih?.id)?.label ?? ''"></span> · {{ __('tanpa stok — tidak menyentuh kartu stok') }}</div>
            </div>
            <button class="btn-close" type="button" x-on:click="tutup()" aria-label="{{ __('Tutup') }}"></button>
        </div>
        <div class="card-body small">
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label mb-0" for="obj-jenis">{{ __('Jenis') }}</label>
                    <select class="form-select form-select-sm" id="obj-jenis" x-model="f.edit.object_type">
                        @foreach ($jenisObjek as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                    </select>
                </div>
                <div class="col-6"><label class="form-label mb-0" for="obj-nama">{{ __('Nama') }}</label><input class="form-control form-control-sm" id="obj-nama" type="text" maxlength="60" x-model="f.edit.name"></div>
                @foreach (['pos_x' => 'X', 'pos_y' => 'Y', 'length_m' => __('Panjang'), 'width_m' => __('Lebar')] as $k => $t)
                    <div class="col-3"><label class="form-label mb-0" for="obj-{{ $k }}">{{ $t }}</label><input class="form-control form-control-sm" id="obj-{{ $k }}" type="number" step="0.1" min="0" x-model="f.edit.{{ $k }}"></div>
                @endforeach
            </div>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <button class="btn btn-sm btn-primary" type="button" x-on:click="terapkanIsian()">{{ __('Terapkan') }}</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" x-on:click="putar()"><i class="bi bi-arrow-clockwise"></i> {{ __('Putar 90°') }}</button>
                <button class="btn btn-sm btn-outline-danger ms-auto" type="button" x-on:click="nonaktifkan()">{{ __('Nonaktifkan') }}</button>
            </div>
        </div>
    </div>
@endif
