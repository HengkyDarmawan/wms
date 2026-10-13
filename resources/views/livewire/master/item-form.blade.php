<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $itemId ? __('Ubah item') : __('Item baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Field bertanda * wajib diisi.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            {{-- A-414: pemandu di layar. --}}
            <x-tur kunci="item-form" :langkah="[
                ['el' => '', 'judul' => __('Membuat item'), 'isi' => __('Item = satu jenis barang yang disimpan di gudang, mis. pipa PVC 4 inci. Cukup isi 4 hal wajib (bertanda *), lalu Simpan. Sisanya boleh diisi nanti.')],
                ['el' => '#item-kode', 'judul' => __('1. Kode item'), 'isi' => __('Kode singkat yang unik, mis. PIPA-PVC4. Dipakai di label & pencarian. Huruf, angka, dan tanda minus.')],
                ['el' => '#item-nama', 'judul' => __('2. Nama item'), 'isi' => __('Nama lengkap yang mudah dikenali staf, mis. Pipa PVC 4 inci AW.')],
                ['el' => '.col-md-4:has(#item-kategori)', 'judul' => __('Kategori (boleh kosong)'), 'isi' => __('Pengelompokan barang, mis. Pipa, Semen, Alat. Buat dulu di Master data → Kategori item bila ingin dipakai.')],
                ['el' => '.col-md-4:has(#item-satuan)', 'judul' => __('3. Satuan dasar'), 'isi' => __('Satuan saat stok dihitung dan dikeluarkan. Barang utuh: BATANG, PCS, SAK. Barang yang dipotong (mis. pipa per meter): M. Setelah ada stok, satuan dasar terkunci.')],
                ['el' => '#jenis-barang', 'judul' => __('4. Jenis barang'), 'isi' => __('Klik satu kartu yang paling cocok. Penjelasan dan contoh barangnya tertulis di tiap kartu.')],
                ['el' => '#pengaturan-tambahan', 'judul' => __('Pengaturan tambahan (opsional)'), 'isi' => __('Isian pembelian otomatis, ukuran barang (mis. 1 BATANG = 6 M), dan kemasan (mis. 1 DUS = 12 PCS). Boleh dilewati, bisa diisi nanti.')],
                ['el' => '[data-tur-simpan]', 'judul' => __('5. Simpan'), 'isi' => __('Klik Simpan item. Dianggap selesai bila muncul tulisan Item disimpan di halaman detail item.')],
            ]" />
            <a class="btn btn-outline-secondary" href="{{ route('items.index') }}">{{ __('Kembali ke daftar') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Identitas') }}</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label" for="item-kode">{{ __('Kode item') }} <span class="wajib">*</span></label>
                <input class="form-control @error('form.code') is-invalid @enderror" id="item-kode" type="text"
                       wire:model="form.code" @disabled($itemId !== null)>
                @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @if ($itemId)
                    <div class="form-text">{{ __('Kode tidak bisa diubah setelah item dibuat.') }}</div>
                @endif
            </div>

            <div class="col-md-5">
                <label class="form-label" for="item-nama">{{ __('Nama item') }} <span class="wajib">*</span></label>
                <input class="form-control @error('form.name') is-invalid @enderror" id="item-nama" type="text"
                       wire:model="form.name">
                @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <x-pilih model="form.item_category_id" id="item-kategori" :label="__('Kategori')" :kosong="__('Tanpa kategori')"
                         :options="$categories->map(fn ($c) => ['value' => $c->id, 'text' => $c->name])->all()" />
            </div>

            <div class="col-md-4">
                <x-pilih model="form.base_uom_id" id="item-satuan" live wajib :label="__('Satuan dasar')" :disabled="$baseUomLocked"
                         :kosong="__('Pilih satuan…')"
                         :options="$uoms->map(fn ($u) => ['value' => $u->id, 'text' => $u->code.' — '.$u->name, 'sub' => $u->category?->name])->all()" />
                {{-- A-355: arti satuan dasar dijelaskan supaya kemasan tidak terbaca terbalik. --}}
                <div class="form-text" id="item-satuan-bantuan">{{ __('Satuan terkecil yang dikeluarkan ke proyek. Stok dihitung dalam satuan ini.') }}</div>
                @if ($alasanKunciDasar)
                    <div class="form-text text-warning-emphasis">{{ __($alasanKunciDasar) }} {{ __('Kemasan tetap boleh ditambah.') }}</div>
                @endif
            </div>

            <div class="col-md-4">
                <label class="form-label" for="item-status">{{ __('Status') }} <span class="wajib">*</span></label>
                <select class="form-select" id="item-status" wire:model="form.status">
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="item-barcode">{{ __('Barcode') }}</label>
                <input class="form-control" id="item-barcode" type="text" wire:model="form.barcode">
            </div>

            @if ($qcAktif)
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" id="item-qc" type="checkbox" wire:model="form.requires_qc">
                        <label class="form-check-label" for="item-qc">{{ __('Wajib melewati QC saat diterima') }}</label>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- A-283: satu pilihan Jenis barang menggantikan isian teknis. --}}
    <div class="card mb-3" id="jenis-barang">
        <div class="card-header">
            <strong>{{ __('Jenis barang') }}</strong> <span class="wajib">*</span>
        </div>
        <div class="card-body">
            @if ($itemKhusus)
                <div class="alert alert-info mb-3" role="status">
                    <div class="fw-semibold">{{ __('Jenis khusus') }}</div>
                    {{ __('Item ini dibuat dengan pengaturan lama di luar tiga jenis barang. Pengaturannya hanya bisa dilihat; item tetap bisa dipakai seperti biasa.') }}
                </div>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">{{ __('Mode pelacakan') }}</dt>
                    <dd class="col-sm-8">{{ $itemKhusus->tracking_mode->label() }}</dd>
                    <dt class="col-sm-4">{{ __('Model kepemilikan') }}</dt>
                    <dd class="col-sm-8">{{ $itemKhusus->ownership_model->label() }}</dd>
                    <dt class="col-sm-4">{{ __('Beli/Pinjam saat diminta') }}</dt>
                    <dd class="col-sm-8">{{ $itemKhusus->defaultLineOwnershipValue() === 'loan' ? __('Pinjam') : __('Beli') }}@if ($itemKhusus->ownership_model->value === 'both') {{ __('(bisa diubah per baris permintaan)') }}@endif</dd>
                    <dt class="col-sm-4">{{ __('Tanggal kedaluwarsa') }}</dt>
                    <dd class="col-sm-8">{{ $itemKhusus->has_expiry ? __('Ya') : __('Tidak') }}</dd>
                    <dt class="col-sm-4">{{ __('Strategi pengambilan') }}</dt>
                    <dd class="col-sm-8">{{ $itemKhusus->effectiveRemovalStrategy()->label() }}</dd>
                    @if ($itemKhusus->is_cuttable)
                        <dt class="col-sm-4">{{ __('Bisa dipotong') }}</dt>
                        <dd class="col-sm-8">{{ __('Ya') }} · {{ __('panjang minimum offcut') }} {{ $itemKhusus->min_offcut_length ?? '—' }} · {{ __('kerf') }} {{ $itemKhusus->kerf ?? '—' }}</dd>
                    @endif
                </dl>
            @else
                <div class="row g-2">
                    @foreach ($jenisOpsi as $nilai => $jenis)
                        <div class="col-md-4">
                            <input class="btn-check" type="radio" id="item-jenis-{{ $nilai }}" value="{{ $nilai }}"
                                   wire:model.live="form.item_kind" autocomplete="off" @disabled($jenisTerkunci)>
                            <label class="btn btn-outline-primary w-100 h-100 text-start py-2" for="item-jenis-{{ $nilai }}">
                                <span class="fw-semibold d-block">{{ $jenis->label() }}</span>
                                <span class="small d-block" style="white-space: normal">{{ $jenis->hint() }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('form.item_kind') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($jenisTerkunci)
                    <div class="form-text">{{ __('Terkunci karena item ini sudah punya pergerakan stok.') }}</div>
                @endif
                @if ($jenisTerpilih)
                    <div class="form-text mt-2">
                        {{ __('Saat diminta proyek:') }}
                        <strong>{{ $jenisTerpilih->ownershipModel()->value === 'asset' ? __('Pinjam — kembali ke gudang') : __('Beli — tidak kembali') }}</strong>.
                    </div>
                @endif
            @endif

            @foreach (['form.tracking_mode', 'form.ownership_model', 'form.has_expiry', 'form.removal_strategy', 'form.min_offcut_length'] as $kunci)
                @error($kunci) <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            @endforeach
        </div>
    </div>

    {{-- Bagian opsional berdampingan sebagai tab agar form utama tidak memanjang ke bawah. --}}
    @php($galat = collect($errors->keys()))
    @php($tabGalat = [
        'stok' => $galat->contains(fn ($k) => in_array($k, ['form.measure_qty', 'form.measure_uom_id', 'form.reorder_point', 'form.min_stock'], true)),
        'konversi' => $galat->contains(fn ($k) => str_starts_with($k, 'form.conversions') || str_starts_with($k, 'conversions')),
    ])
    @php($tabAktif = ! $tabGalat[$tabTambahan] && in_array(true, $tabGalat, true) ? array_search(true, $tabGalat, true) : $tabTambahan)
    <div class="card mb-3" id="pengaturan-tambahan">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-baseline gap-2">
            <strong>{{ __('Pengaturan tambahan') }}</strong>
            <span class="text-muted small">{{ __('Opsional — boleh dilewati, bisa diisi nanti.') }}</span>
        </div>
        <div class="px-3 pt-3">
            <ul class="nav nav-tabs" role="tablist">
                @foreach (['stok' => __('Stok & pembelian otomatis'),'konversi' => __('Kemasan') . ($conversions !== [] ? ' ('.count($conversions).')' : '')] as $kunci => $judul)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if ($tabAktif === $kunci) active @endif" type="button" role="tab" id="tab-{{ $kunci }}"
                                aria-selected="{{ $tabAktif === $kunci ? 'true' : 'false' }}" wire:click="$set('tabTambahan', '{{ $kunci }}')">
                            {{ $judul }}@if ($tabGalat[$kunci]) <span class="badge rounded-pill text-bg-danger ms-1">!</span>@endif
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body">
            @if ($tabAktif === 'stok')
                @php($angkaStok = fn ($v) => is_numeric(str_replace(',', '.', (string) $v)) ? (float) str_replace(',', '.', (string) $v) : null)
                @php($titik = $angkaStok($form['reorder_point']))
                @php($isiSampai = $angkaStok($form['min_stock']))
                @php($satuanStok = $kodeDasar !== '' ? ' '.$kodeDasar : '')
                @php($fmt = fn ($n) => \App\Domain\Master\Support\QtyFormat::number($n))
                <p class="text-muted small mb-3">{{ __('Opsional. Bila diisi, setiap pagi sistem membuat draf permintaan pembelian untuk barang yang stoknya menipis. Kosongkan bila tidak perlu.') }}</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="item-rop">{{ __('Titik pesan ulang') }}</label>
                        <input class="form-control @error('form.reorder_point') is-invalid @enderror" id="item-rop"
                               type="text" inputmode="decimal" wire:model.live.debounce.400ms="form.reorder_point" placeholder="{{ __('mis. 20') }}">
                        <div class="form-text">{{ __('Bila stok tersedia di bawah angka ini → sistem membuat draf pembelian.') }}</div>
                        @error('form.reorder_point') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="item-min-stok">{{ __('Isi sampai (stok ideal)') }}</label>
                        <input class="form-control @error('form.min_stock') is-invalid @enderror" id="item-min-stok"
                               type="text" inputmode="decimal" wire:model.live.debounce.400ms="form.min_stock" placeholder="{{ __('mis. 100') }}">
                        <div class="form-text">{{ __('Jumlah stok yang ingin dicapai setelah membeli.') }}</div>
                        @error('form.min_stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="item-ukuran">{{ $kodeDasar !== '' ? __('Ukuran per 1 :s', ['s' => $kodeDasar]) : __('Ukuran per satuan dasar') }}</label>
                        <div class="d-flex gap-2 align-items-start">
                            <input class="form-control @error('form.measure_qty') is-invalid @enderror" id="item-ukuran"
                                   type="text" inputmode="decimal" wire:model="form.measure_qty" placeholder="{{ __('mis. 6') }}">
                            <x-pilih model="form.measure_uom_id" id="item-ukuran-satuan" style="min-width: 7rem" :aria="__('Satuan ukuran')"
                                     :kosong="__('Satuan')" :kelompok="$uomUkuran->map(fn ($u) => $u->category?->name)->unique()->values()->all()"
                                     :options="$uomUkuran->map(fn ($u) => ['value' => $u->id, 'text' => $u->code, 'group' => $u->category?->name])->all()" />
                        </div>
                        <div class="form-text">{{ __('Informasi saja, mis. 6 M per batang, 12 KG per batang, atau 5 L per kaleng.') }}</div>
                        @error('form.measure_qty') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @error('form.measure_uom_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    @if ($titik !== null && $titik > 0)
                        <div class="col-12" data-contoh-pembelian>
                            @if ($isiSampai !== null && $isiSampai > $titik)
                                @php($sisa = max(0, round($titik / 2, 4)))
                                <div class="alert alert-info py-2 mb-0 small">
                                    {{ __('Contoh: bila stok tersedia tinggal :sisa:s (di bawah :titik:s), besok pagi dibuat draf pembelian :beli:s supaya stok kembali :isi:s.', ['sisa' => $fmt($sisa), 's' => $satuanStok, 'titik' => $fmt($titik), 'beli' => $fmt($isiSampai - $sisa), 'isi' => $fmt($isiSampai)]) }}
                                </div>
                            @else
                                <div class="alert alert-warning py-2 mb-0 small" data-peringatan-isi-sampai>
                                    {{ __('Isi sampai kosong atau tidak lebih besar dari titik pesan ulang, jadi sistem selalu memesan :titik:s setiap kali stok di bawah :titik:s. Isi angka yang lebih besar (mis. :saran) bila ingin stok terisi kembali penuh.', ['titik' => $fmt($titik), 's' => $satuanStok, 'saran' => $fmt($titik * 5)]) }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                {{-- A-355: tiap baris dibaca sebagai kalimat "1 DUS berisi 12 BOX = 12 BOX". --}}
                @php($kodeSatuan = $uoms->pluck('code', 'id'))
                @php($dasarKode = $kodeSatuan[(int) ($form['base_uom_id'] ?: 0)] ?? null)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <span class="text-muted small">{{ __('Kemasan barang saat datang atau diminta. Baca tiap baris sebagai kalimat, mis. "1 DUS berisi 12 BOX". Isi boleh ditulis dengan kemasan lain yang lebih kecil; stok tetap dicatat dalam satuan dasar. Kemasan juga bisa ditambah langsung dari form penerimaan barang.') }}</span>
                    <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahKonversi">{{ __('Tambah kemasan') }}</button>
                </div>
                <div>
                    @error('form.conversions') <div class="alert alert-danger">{{ $message }}</div> @enderror
                    @if ($dasarKode === null && $conversions !== [])
                        <div class="alert alert-warning py-2 small">{{ __('Pilih satuan dasar dulu; isi kemasan dihitung ke satuan itu.') }}</div>
                    @endif

                    @forelse ($conversions as $index => $konversi)
                        @php($hasil = $kemasan['hasil'][$index] ?? null)
                        <div class="border rounded px-2 py-2 mb-2 @error('form.conversions.'.$index) border-danger @enderror" wire:key="konversi-{{ $index }}" data-kalimat-kemasan>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="fw-semibold">1</span>
                                <x-pilih model="conversions.{{ $index }}.uom_id" id="konversi-satuan-{{ $index }}" live kecil style="min-width: 10rem"
                                         :aria="__('Satuan kemasan')" :kosong="__('Kemasan…')"
                                         :options="$uoms->reject(fn ($u) => (string) $u->id === (string) $form['base_uom_id'])
                                             ->map(fn ($u) => ['value' => $u->id, 'text' => $u->code.' — '.$u->name])->all()" />

                                <span>{{ __('berisi') }}</span>
                                <input class="form-control form-control-sm" style="width: 6.5rem" id="konversi-qty-{{ $index }}" type="text" inputmode="decimal"
                                       wire:model.live.debounce.300ms="conversions.{{ $index }}.content_qty" aria-label="{{ __('Jumlah isi') }}">
                                <select class="form-select form-select-sm w-auto" style="min-width: 7rem" id="konversi-isi-{{ $index }}"
                                        wire:model.live="conversions.{{ $index }}.content_uom_id" aria-label="{{ __('Satuan isi') }}">
                                    <option value="">{{ $dasarKode ?? __('Satuan dasar') }}</option>
                                    @foreach ($conversions as $j => $lain)
                                        @continue($j === $index || ($lain['uom_id'] ?? '') === '' || ! isset($kodeSatuan[(int) $lain['uom_id']]))
                                        <option value="{{ $lain['uom_id'] }}">{{ $kodeSatuan[(int) $lain['uom_id']] }}</option>
                                    @endforeach
                                </select>
                                @if ($hasil)
                                    <span class="text-nowrap">= <strong data-hasil-kemasan>{{ $hasil['hasil'] }}</strong>@if ($hasil['rincian']) <span class="text-muted small">({{ $hasil['rincian'] }})</span>@endif</span>
                                @endif
                                <button class="btn btn-sm btn-outline-danger ms-auto" type="button"
                                        wire:click="hapusKonversi({{ $index }})">{{ __('Hapus') }}</button>
                            </div>
                            {{-- A-284: tanda batang utuh hanya berarti untuk fitur per potong. --}}
                            @if ($pieceAktif)
                                <div class="form-check mt-1 mb-0">
                                    <input class="form-check-input" id="konversi-nominal-{{ $index }}" type="checkbox"
                                           wire:model="conversions.{{ $index }}.is_nominal_piece">
                                    <label class="form-check-label small" for="konversi-nominal-{{ $index }}">
                                        {{ __('Panjang nominal batang utuh') }}
                                    </label>
                                </div>
                            @endif
                            @error('form.conversions.'.$index) <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('Belum ada kemasan.') }}</p>
                    @endforelse

                    @foreach ($kemasan['berubah'] as $catatan)
                        <div class="small text-warning-emphasis">{{ $catatan }} {{ __('Dokumen yang sudah dibuat tidak berubah.') }}</div>
                    @endforeach
                    @if ($kemasan['contoh'])
                        <div class="small text-muted mt-2" data-contoh-kemasan>{{ $kemasan['contoh'] }}</div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button class="btn btn-primary" type="button" wire:click="simpan" data-tur-simpan>{{ __('Simpan item') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('items.index') }}">{{ __('Batal') }}</a>
    </div>
</div>
