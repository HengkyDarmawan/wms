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

    @if ($petunjuk !== [])
        <div class="alert alert-warning" role="alert">
            <div class="fw-semibold mb-1">{{ __('Kombinasi ini belum bisa disimpan:') }}</div>
            <ul class="mb-0 ps-3">
                @foreach ($petunjuk as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
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

            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" id="item-qc" type="checkbox" wire:model="form.requires_qc">
                    <label class="form-check-label" for="item-qc">{{ __('Wajib melewati QC saat diterima') }}</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <strong>{{ __('Pelacakan & kepemilikan') }}</strong>
            <span class="text-muted small ms-2">
                {{ __('Pilihan di bawah saling terikat; yang tidak berlaku otomatis hilang.') }}
            </span>
        </div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="item-pelacakan">{{ __('Mode pelacakan') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.tracking_mode') is-invalid @enderror" id="item-pelacakan"
                        wire:model.live="form.tracking_mode">
                    @foreach ($trackingModes as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.tracking_mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="item-kepemilikan">{{ __('Model kepemilikan') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.ownership_model') is-invalid @enderror" id="item-kepemilikan"
                        wire:model.live="form.ownership_model">
                    @foreach ($ownerships as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.ownership_model') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">{{ __('Aset yang dipinjamkan wajib memakai Serial number.') }}</div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="item-baris-kepemilikan">{{ __('Sifat baris bawaan') }}</label>
                <select class="form-select" id="item-baris-kepemilikan" wire:model="form.default_line_ownership">
                    <option value="">{{ __('Ditentukan per baris') }}</option>
                    @foreach ($lineOwnerships as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="item-strategi">{{ __('Strategi pengambilan') }}</label>
                <select class="form-select @error('form.removal_strategy') is-invalid @enderror" id="item-strategi"
                        wire:model.live="form.removal_strategy">
                    <option value="">{{ __('Ikuti kategori') }}</option>
                    @foreach ($strategies as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.removal_strategy') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="item-satuan">{{ __('Satuan dasar') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.base_uom_id') is-invalid @enderror" id="item-satuan"
                        wire:model.live="form.base_uom_id" @disabled($baseUomLocked)>
                    <option value="">{{ __('Pilih satuan…') }}</option>
                    @foreach ($uoms as $uom)
                        <option value="{{ $uom->id }}">{{ $uom->code }} — {{ $uom->name }} ({{ $uom->category?->name }})</option>
                    @endforeach
                </select>
                @error('form.base_uom_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @if ($baseUomLocked)
                    <div class="form-text">
                        {{ __('Terkunci karena item ini sudah punya lot, serial, atau potongan.') }}
                    </div>
                @endif
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input @error('form.has_expiry') is-invalid @enderror" id="item-kedaluwarsa"
                           type="checkbox" wire:model.live="form.has_expiry" @disabled(! $bolehKedaluwarsa)>
                    <label class="form-check-label" for="item-kedaluwarsa">{{ __('Punya tanggal kedaluwarsa') }}</label>
                    @unless ($bolehKedaluwarsa)
                        <div class="form-text">{{ __('Hanya untuk Batch/Lot atau Serial number.') }}</div>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Pemotongan & ambang stok') }}</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" id="item-potong" type="checkbox" wire:model.live="form.is_cuttable">
                    <label class="form-check-label" for="item-potong">{{ __('Bisa dipotong') }}</label>
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="item-offcut">
                    {{ __('Panjang minimum offcut') }} @if ($form['is_cuttable']) <span class="wajib">*</span> @endif
                </label>
                <input class="form-control @error('form.min_offcut_length') is-invalid @enderror" id="item-offcut"
                       type="text" wire:model.live.debounce.500ms="form.min_offcut_length"
                       @disabled(! $form['is_cuttable'])>
                @error('form.min_offcut_length') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">{{ __('Sisa di bawah angka ini dicatat sebagai waste.') }}</div>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="item-kerf">{{ __('Susut mata potong (kerf)') }}</label>
                <input class="form-control @error('form.kerf') is-invalid @enderror" id="item-kerf" type="text"
                       wire:model="form.kerf" @disabled(! $form['is_cuttable'])>
                @error('form.kerf') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong>{{ __('Konversi satuan khusus') }}</strong>
                <span class="text-muted small ms-2">{{ __('mis. 1 batang = 6 meter. Hanya untuk input dokumen.') }}</span>
            </div>
            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahKonversi">
                {{ __('Tambah baris') }}
            </button>
        </div>
        <div class="card-body">
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
                            {{ __('Setara berapa satuan dasar') }} <span class="wajib">*</span>
                        </label>
                        <input class="form-control" id="konversi-qty-{{ $index }}" type="text"
                               wire:model="conversions.{{ $index }}.qty_base">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" id="konversi-nominal-{{ $index }}" type="checkbox"
                                   wire:model="conversions.{{ $index }}.is_nominal_piece">
                            <label class="form-check-label" for="konversi-nominal-{{ $index }}">
                                {{ __('Panjang nominal batang utuh') }}
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 text-end">
                        <button class="btn btn-sm btn-outline-danger" type="button"
                                wire:click="hapusKonversi({{ $index }})">{{ __('Hapus') }}</button>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">{{ __('Belum ada konversi khusus.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong>{{ __('Vendor tetap') }}</strong>
                <span class="text-muted small ms-2">{{ __('Urutan prioritas pemasok. Tanpa harga.') }}</span>
            </div>
            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahVendor">
                {{ __('Tambah vendor') }}
            </button>
        </div>
        <div class="card-body">
            @forelse ($vendorRows as $index => $baris)
                <div class="row g-2 align-items-end mb-2" wire:key="vendor-baris-{{ $index }}">
                    <div class="col-md-4">
                        <label class="form-label" for="vendor-pilih-{{ $index }}">{{ __('Vendor') }} <span class="wajib">*</span></label>
                        <select class="form-select" id="vendor-pilih-{{ $index }}"
                                wire:model="vendorRows.{{ $index }}.vendor_id">
                            <option value="">{{ __('Pilih vendor…') }}</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="vendor-prioritas-{{ $index }}">{{ __('Prioritas') }}</label>
                        <input class="form-control" id="vendor-prioritas-{{ $index }}" type="number" min="1"
                               wire:model="vendorRows.{{ $index }}.priority">
                    </div>
                    <div class="col-md-2">
                        <div class="form-check">
                            <input class="form-check-input" id="vendor-utama-{{ $index }}" type="checkbox"
                                   wire:model="vendorRows.{{ $index }}.is_preferred">
                            <label class="form-check-label" for="vendor-utama-{{ $index }}">{{ __('Utama') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="vendor-catatan-{{ $index }}">{{ __('Catatan') }}</label>
                        <input class="form-control" id="vendor-catatan-{{ $index }}" type="text"
                               wire:model="vendorRows.{{ $index }}.notes">
                    </div>
                    <div class="col-md-1 text-end">
                        <button class="btn btn-sm btn-outline-danger" type="button"
                                wire:click="hapusVendor({{ $index }})">{{ __('Hapus') }}</button>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">{{ __('Belum ada vendor tetap.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan item') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('items.index') }}">{{ __('Batal') }}</a>
    </div>
</div>
