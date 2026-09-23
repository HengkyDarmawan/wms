<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Satuan') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Konversi hanya sah di dalam satu kategori. Setiap kategori punya satu satuan acuan berfaktor 1.') }}
            </p>
        </div>

        @can('create', \App\Domain\Master\Models\UomCategory::class)
            <button class="btn btn-primary" type="button" wire:click="buatKategori">
                <i class="bi bi-plus-lg"></i> {{ __('Tambah kategori satuan') }}
            </button>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    @if ($showCategoryForm)
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ $editingCategoryId ? __('Ubah kategori satuan') : __('Kategori satuan baru') }}</strong>
                <span class="text-muted small ms-2">{{ __('Field bertanda * wajib diisi.') }}</span>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="kat-satuan-kode">{{ __('Kode kategori') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('categoryForm.code') is-invalid @enderror" id="kat-satuan-kode"
                           type="text" wire:model="categoryForm.code" @disabled($editingCategoryId !== null)>
                    @error('categoryForm.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-5">
                    <label class="form-label" for="kat-satuan-nama">{{ __('Nama kategori') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('categoryForm.name') is-invalid @enderror" id="kat-satuan-nama"
                           type="text" wire:model="categoryForm.name">
                    @error('categoryForm.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                @if ($editingCategoryId)
                    <div class="col-md-4">
                        <label class="form-label" for="kat-satuan-acuan">{{ __('Satuan acuan') }} <span class="wajib">*</span></label>
                        <select class="form-select @error('categoryForm.reference_uom_id') is-invalid @enderror"
                                id="kat-satuan-acuan" wire:model="categoryForm.reference_uom_id">
                            <option value="">{{ __('Pilih satuan acuan…') }}</option>
                            @foreach ($uoms as $uom)
                                <option value="{{ $uom->id }}">{{ $uom->code }} — {{ $uom->name }}</option>
                            @endforeach
                        </select>
                        @error('categoryForm.reference_uom_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @else
                    <div class="col-md-2">
                        <label class="form-label" for="kat-acuan-kode">{{ __('Kode satuan acuan') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('categoryForm.reference_code') is-invalid @enderror"
                               id="kat-acuan-kode" type="text" wire:model="categoryForm.reference_code">
                        @error('categoryForm.reference_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="kat-acuan-nama">{{ __('Nama satuan acuan') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('categoryForm.reference_name') is-invalid @enderror"
                               id="kat-acuan-nama" type="text" wire:model="categoryForm.reference_name">
                        @error('categoryForm.reference_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">{{ __('Satuan acuan dibuat otomatis dengan faktor 1.') }}</div>
                    </div>
                @endif
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpanKategori">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalKategori">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>{{ __('Kategori satuan') }}</strong></div>
                <div class="list-group list-group-flush">
                    @forelse ($categories as $kategori)
                        <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $activeCategoryId === $kategori->id ? 'active' : '' }}"
                                type="button" wire:key="kat-{{ $kategori->id }}"
                                wire:click="pilihKategori({{ $kategori->id }})">
                            <span>
                                <span class="fw-semibold">{{ $kategori->name }}</span>
                                <span class="small d-block">{{ $kategori->code }}</span>
                            </span>
                            <span class="badge text-bg-light">{{ $kategori->uoms_count }}</span>
                        </button>
                    @empty
                        <div class="list-group-item text-muted">{{ __('Belum ada kategori satuan.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            @if ($kategoriAktif)
                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <strong>{{ $kategoriAktif->name }}</strong>
                            <span class="text-muted small ms-2">
                                {{ __('Satuan acuan:') }}
                                {{ $kategoriAktif->referenceUom?->code ?? __('belum ditetapkan') }}
                            </span>
                        </div>
                        <div class="d-flex gap-2">
                            @can('update', $kategoriAktif)
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        wire:click="ubahKategori({{ $kategoriAktif->id }})">
                                    {{ __('Ubah kategori') }}
                                </button>
                            @endcan
                            @can('create', \App\Domain\Master\Models\Uom::class)
                                <button class="btn btn-sm btn-primary" type="button" wire:click="buatSatuan">
                                    {{ __('Tambah satuan') }}
                                </button>
                            @endcan
                        </div>
                    </div>

                    @if ($showUomForm)
                        <div class="card-body border-bottom">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label" for="satuan-kode">{{ __('Kode') }} <span class="wajib">*</span></label>
                                    <input class="form-control @error('form.code') is-invalid @enderror"
                                           id="satuan-kode" type="text" wire:model="form.code"
                                           @disabled($editingUomId !== null)>
                                    @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="satuan-nama">{{ __('Nama') }} <span class="wajib">*</span></label>
                                    <input class="form-control @error('form.name') is-invalid @enderror"
                                           id="satuan-nama" type="text" wire:model="form.name">
                                    @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="satuan-faktor">
                                        {{ __('Setara berapa satuan acuan') }} <span class="wajib">*</span>
                                    </label>
                                    <input class="form-control @error('form.factor_to_reference') is-invalid @enderror"
                                           id="satuan-faktor" type="text" wire:model="form.factor_to_reference">
                                    @error('form.factor_to_reference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="satuan-pembulatan">{{ __('Pembulatan') }}</label>
                                    <input class="form-control @error('form.rounding') is-invalid @enderror"
                                           id="satuan-pembulatan" type="text" wire:model="form.rounding">
                                    @error('form.rounding') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button class="btn btn-primary" type="button" wire:click="simpanSatuan">
                                    {{ __('Simpan satuan') }}
                                </button>
                                <button class="btn btn-outline-secondary" type="button" wire:click="batalSatuan">
                                    {{ __('Batal') }}
                                </button>
                            </div>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Satuan') }}</th>
                                    <th class="text-end">{{ __('Faktor ke acuan') }}</th>
                                    <th class="text-end">{{ __('Pembulatan') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-end">{{ __('Aksi') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($uoms as $uom)
                                    <tr wire:key="satuan-{{ $uom->id }}">
                                        <td>
                                            <span class="fw-semibold">{{ $uom->code }}</span>
                                            <span class="text-muted">— {{ $uom->name }}</span>
                                            @if ($kategoriAktif->reference_uom_id === $uom->id)
                                                <span class="badge text-bg-info">{{ __('Acuan') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $uom->factor_to_reference, 8, '.', ''), '0'), '.') }}</td>
                                        <td class="text-end">{{ $uom->rounding ? (float) $uom->rounding : '—' }}</td>
                                        <td>
                                            <span class="badge text-bg-{{ $uom->is_active ? 'success' : 'secondary' }}">
                                                {{ $uom->is_active ? __('Aktif') : __('Nonaktif') }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @can('update', $uom)
                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                        wire:click="ubahSatuan({{ $uom->id }})">{{ __('Ubah') }}</button>
                                            @endcan
                                            @if ($uom->is_active)
                                                @can('deactivate', $uom)
                                                    <button class="btn btn-sm btn-outline-danger" type="button"
                                                            wire:click="nonaktifkanSatuan({{ $uom->id }})">
                                                        {{ __('Nonaktifkan') }}
                                                    </button>
                                                @endcan
                                            @else
                                                @can('update', $uom)
                                                    <button class="btn btn-sm btn-outline-success" type="button"
                                                            wire:click="aktifkanSatuan({{ $uom->id }})">
                                                        {{ __('Aktifkan') }}
                                                    </button>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            {{ __('Belum ada satuan di kategori ini.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-muted">{{ __('Pilih kategori satuan di sebelah kiri.') }}</div>
                </div>
            @endif
        </div>
    </div>
</div>
