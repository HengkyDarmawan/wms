<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $itemId ? __('Ubah item') : __('Item baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Field bertanda * wajib diisi.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('items.index') }}">{{ __('Kembali ke daftar') }}</a>
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
                <label class="form-label" for="item-kategori">{{ __('Kategori') }}</label>
                <select class="form-select" id="item-kategori" wire:model="form.item_category_id">
                    <option value="">{{ __('Tanpa kategori') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="item-satuan">{{ __('Satuan dasar') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.base_uom_id') is-invalid @enderror" id="item-satuan"
                        wire:model="form.base_uom_id" @disabled($baseUomLocked)>
                    <option value="">{{ __('Pilih satuan…') }}</option>
                    @foreach ($uoms as $uom)
                        <option value="{{ $uom->id }}">{{ $uom->code }} — {{ $uom->name }} ({{ $uom->category?->name }})</option>
                    @endforeach
                </select>
                @error('form.base_uom_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @if ($baseUomLocked)
                    <div class="form-text">{{ __('Terkunci karena item ini sudah punya lot, serial, atau potongan.') }}</div>
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
        'stok' => $galat->contains(fn ($k) => in_array($k, ['form.weight', 'form.reorder_point', 'form.min_stock'], true)),
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
                @foreach (['stok' => __('Stok minimum'), 'konversi' => __('Kemasan') . ($conversions !== [] ? ' ('.count($conversions).')' : '')] as $kunci => $judul)
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
                <p class="text-muted small">{{ __('Titik pesan ulang & stok minimum memicu pengingat pembelian.') }}</p>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="item-rop">{{ __('Titik pesan ulang') }}</label>
                        <input class="form-control @error('form.reorder_point') is-invalid @enderror" id="item-rop"
                               type="text" wire:model="form.reorder_point">
                        @error('form.reorder_point') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="item-min-stok">{{ __('Stok minimum') }}</label>
                        <input class="form-control @error('form.min_stock') is-invalid @enderror" id="item-min-stok"
                               type="text" wire:model="form.min_stock">
                        @error('form.min_stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="item-berat">{{ __('Berat per satuan dasar') }}</label>
                        <div class="input-group">
                            <input class="form-control @error('form.weight') is-invalid @enderror" id="item-berat"
                                   type="text" wire:model="form.weight">
                            <select class="form-select" wire:model="form.weight_uom_id"
                                    aria-label="{{ __('Satuan berat') }}">
                                <option value="">{{ __('Satuan') }}</option>
                                @foreach ($uoms as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('form.weight') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>
            @else
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <span class="text-muted small">{{ __('Kemasan barang saat datang atau diminta, mis. 1 dus = 12 box. Stok tetap dicatat dalam satuan dasar; kemasan juga bisa ditambah langsung dari form penerimaan barang.') }}</span>
                    <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahKonversi">{{ __('Tambah baris') }}</button>
                </div>
                <div>
                    @error('form.conversions') <div class="alert alert-danger">{{ $message }}</div> @enderror

                    @forelse ($conversions as $index => $konversi)
                        <div class="row g-2 align-items-end mb-2" wire:key="konversi-{{ $index }}">
                            <div class="col-md-4">
                                <label class="form-label" for="konversi-satuan-{{ $index }}">{{ __('Satuan') }} <span class="wajib">*</span></label>
                                <select class="form-select" id="konversi-satuan-{{ $index }}"
                                        wire:model="conversions.{{ $index }}.uom_id">
                                    <option value="">{{ __('Pilih satuan…') }}</option>
                                    @foreach ($uoms as $uom)
                                        <option value="{{ $uom->id }}">{{ $uom->code }} — {{ $uom->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="konversi-qty-{{ $index }}">
                                    {{ __('Isi dalam satuan dasar') }} <span class="wajib">*</span>
                                </label>
                                <input class="form-control" id="konversi-qty-{{ $index }}" type="text"
                                       wire:model="conversions.{{ $index }}.qty_base">
                            </div>
                            <div class="col-md-3">
                                {{-- A-284: tanda batang utuh hanya berarti untuk fitur per potong. --}}
                                @if ($pieceAktif)
                                    <div class="form-check">
                                        <input class="form-check-input" id="konversi-nominal-{{ $index }}" type="checkbox"
                                               wire:model="conversions.{{ $index }}.is_nominal_piece">
                                        <label class="form-check-label" for="konversi-nominal-{{ $index }}">
                                            {{ __('Panjang nominal batang utuh') }}
                                        </label>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-2 text-end">
                                <button class="btn btn-sm btn-outline-danger" type="button"
                                        wire:click="hapusKonversi({{ $index }})">{{ __('Hapus') }}</button>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('Belum ada kemasan.') }}</p>
                    @endforelse
                </div>
            @endif
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan item') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('items.index') }}">{{ __('Batal') }}</a>
    </div>
</div>
