<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Satuan') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Contoh: 1 M = 100 CM, 1 LSN = 12 PCS. Satuan hanya bisa dikonversi dengan satuan sekategori (panjang dengan panjang, berat dengan berat).') }}
            </p>
        </div>

        {{-- A-414: pemandu di layar. --}}
        <x-tur kunci="satuan" class="ms-auto" :langkah="[
            ['el' => '', 'judul' => __('Satuan'), 'isi' => __('Satuan umum (PCS, BATANG, M, KG, L, dll.) sudah disiapkan otomatis. Biasanya tidak perlu menambah apa pun.')],
            ['el' => '[data-kategori-satuan]', 'judul' => __('Kategori satuan'), 'isi' => __('Satuan dikelompokkan: Hitung, Panjang, Berat, Volume, Luas. Hanya satuan sekategori yang bisa dikonversi, mis. M ke CM.')],
            ['el' => '[data-tambah-satuan]', 'judul' => __('Tambah satuan'), 'isi' => __('Bila perlu satuan baru: isi kode dan nama, lalu tulis kalimatnya, mis. 1 KM = 1000 M. Kemasan (DUS, BOX) isinya diatur per barang di form item.')],
        ]" />
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
                        <x-pilih model="categoryForm.reference_uom_id" id="kat-satuan-acuan" wajib :label="__('Satuan acuan')"
                                 :kosong="__('Pilih satuan acuan…')"
                                 :options="$uoms->map(fn ($u) => ['value' => $u->id, 'text' => $u->code.' — '.$u->name])->all()" />
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
                <div class="list-group list-group-flush" data-kategori-satuan>
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
                                <button class="btn btn-sm btn-primary" type="button" wire:click="buatSatuan" data-tambah-satuan>
                                    {{ __('Tambah satuan') }}
                                </button>
                            @endcan
                        </div>
                    </div>

                    @if ($showUomForm)
                        @php
                            $kode = trim($form['code']) !== '' ? mb_strtoupper(trim($form['code'])) : __('satuan ini');
                            $namaPembanding = $pembanding->firstWhere('id', (int) $form['pembanding'])?->code ?? '…';
                        @endphp
                        <div class="card-body border-bottom" data-form-satuan>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label" for="satuan-kode">{{ __('Kode') }} <span class="wajib">*</span></label>
                                    <input class="form-control @error('form.code') is-invalid @enderror" id="satuan-kode" type="text" maxlength="15"
                                           wire:model.live.debounce.400ms="form.code" placeholder="KM" @disabled($editingUomId !== null)>
                                    @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label" for="satuan-nama">{{ __('Nama') }} <span class="wajib">*</span></label>
                                    <input class="form-control @error('form.name') is-invalid @enderror" id="satuan-nama" type="text" maxlength="60" wire:model="form.name" placeholder="{{ __('Kilometer') }}">
                                    @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                @if ($formDasar)
                                    <div class="col-12 small text-muted">{{ __('Ini satuan acuan kategori — satuan lain dihitung terhadapnya, jadi tidak punya "setara".') }}</div>
                                @else
                                    <div class="col-12">
                                        <span class="form-label d-block">{{ __('Setara') }} <span class="wajib">*</span></span>
                                        <div class="d-flex flex-column gap-2">
                                            <label class="d-flex flex-wrap align-items-center gap-2">
                                                <input class="form-check-input mt-0" type="radio" value="besar" wire:model.live="form.mode">
                                                <span>1 <strong>{{ $kode }}</strong> =</span>
                                                @if ($form['mode'] === 'besar')
                                                    <input class="form-control form-control-sm @error('form.angka') is-invalid @enderror" style="width: 8rem" type="text" inputmode="decimal" wire:model="form.angka" placeholder="1000" aria-label="{{ __('Angka setara') }}">
                                                    <select class="form-select form-select-sm w-auto @error('form.pembanding') is-invalid @enderror" wire:model.live="form.pembanding" aria-label="{{ __('Satuan pembanding') }}">
                                                        <option value="">{{ __('satuan…') }}</option>
                                                        @foreach ($pembanding as $u) <option value="{{ $u->id }}">{{ $u->code }}</option> @endforeach
                                                    </select>
                                                @else
                                                    <span class="text-muted">… {{ __('(satuan lebih besar, mis. 1 KM = 1000 M)') }}</span>
                                                @endif
                                            </label>
                                            <label class="d-flex flex-wrap align-items-center gap-2">
                                                <input class="form-check-input mt-0" type="radio" value="kecil" wire:model.live="form.mode">
                                                @if ($form['mode'] === 'kecil')
                                                    <span>1</span>
                                                    <select class="form-select form-select-sm w-auto @error('form.pembanding') is-invalid @enderror" wire:model.live="form.pembanding" aria-label="{{ __('Satuan pembanding') }}">
                                                        <option value="">{{ __('satuan…') }}</option>
                                                        @foreach ($pembanding as $u) <option value="{{ $u->id }}">{{ $u->code }}</option> @endforeach
                                                    </select>
                                                    <span>=</span>
                                                    <input class="form-control form-control-sm @error('form.angka') is-invalid @enderror" style="width: 8rem" type="text" inputmode="decimal" wire:model="form.angka" placeholder="100" aria-label="{{ __('Angka setara') }}">
                                                    <strong>{{ $kode }}</strong>
                                                @else
                                                    <span>1 … = … <strong>{{ $kode }}</strong> <span class="text-muted">{{ __('(satuan lebih kecil, mis. 1 M = 100 CM)') }}</span></span>
                                                @endif
                                            </label>
                                            <label class="d-flex flex-wrap align-items-center gap-2">
                                                <input class="form-check-input mt-0" type="radio" value="kemasan" wire:model.live="form.mode">
                                                <span>{{ __('Kemasan — isinya beda tiap barang (mis. DUS, BOX); diatur di tab Kemasan barang') }}</span>
                                            </label>
                                        </div>
                                        @error('form.angka') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        @error('form.pembanding') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                    </div>
                                @endif

                                <div class="col-12">
                                    <details>
                                        <summary class="small text-muted">{{ __('Pengaturan lanjutan') }}</summary>
                                        <div class="mt-2" style="max-width: 16rem">
                                            <label class="form-label small" for="satuan-pembulatan">{{ __('Pembulatan jumlah (opsional)') }}</label>
                                            <input class="form-control form-control-sm @error('form.rounding') is-invalid @enderror" id="satuan-pembulatan" type="text" wire:model="form.rounding" placeholder="{{ __('mis. 0,01') }}">
                                            @error('form.rounding') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </details>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button class="btn btn-primary" type="button" wire:click="simpanSatuan">{{ __('Simpan satuan') }}</button>
                                <button class="btn btn-outline-secondary" type="button" wire:click="batalSatuan">{{ __('Batal') }}</button>
                            </div>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Satuan') }}</th>
                                    <th>{{ __('Setara') }}</th>
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
                                                <span class="badge text-bg-info">{{ __('Satuan acuan') }}</span>
                                            @elseif ($uom->is_packaging)
                                                <span class="badge text-bg-secondary">{{ __('Kemasan') }}</span>
                                            @endif
                                        </td>
                                        <td data-setara="{{ $uom->code }}">
                                            @if ($kategoriAktif->reference_uom_id === $uom->id)
                                                <span class="text-muted">{{ __('satuan acuan') }}</span>
                                            @elseif ($uom->is_packaging)
                                                <span class="text-muted">{{ __('isi ditentukan per barang') }}</span>
                                            @else
                                                <span class="fw-semibold">{{ $uom->kalimatSetara($kategoriAktif->referenceUom) }}</span>
                                            @endif
                                        </td>
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
                                        <td colspan="4" class="text-center text-muted py-4">
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
