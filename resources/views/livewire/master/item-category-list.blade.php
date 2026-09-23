<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Kategori item') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Kategori mewariskan kategori penyimpanan, strategi pengambilan, dan toleransi opname ke itemnya.') }}
            </p>
        </div>

        @can('create', \App\Domain\Master\Models\ItemCategory::class)
            <button class="btn btn-primary" type="button" wire:click="buat">
                <i class="bi bi-plus-lg"></i> {{ __('Tambah kategori') }}
            </button>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    @if ($showForm)
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ $editingId ? __('Ubah kategori') : __('Kategori baru') }}</strong>
                <span class="text-muted small ms-2">{{ __('Field bertanda * wajib diisi.') }}</span>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="kategori-kode">{{ __('Kode') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.code') is-invalid @enderror" id="kategori-kode"
                           type="text" wire:model="form.code" @disabled($editingId !== null)>
                    @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-5">
                    <label class="form-label" for="kategori-nama">{{ __('Nama kategori') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.name') is-invalid @enderror" id="kategori-nama"
                           type="text" wire:model="form.name">
                    @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="kategori-induk">{{ __('Induk') }}</label>
                    <select class="form-select" id="kategori-induk" wire:model="form.parent_id">
                        <option value="">{{ __('Kategori tingkat atas') }}</option>
                        @foreach ($semua as $kategori)
                            @continue($editingId === $kategori->id)
                            <option value="{{ $kategori->id }}">{{ $kategori->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="kategori-penyimpanan">{{ __('Kategori penyimpanan bawaan') }}</label>
                    <select class="form-select" id="kategori-penyimpanan" wire:model="form.storage_category_id">
                        <option value="">{{ __('Tidak ditentukan') }}</option>
                        @foreach ($storageCategories as $penyimpanan)
                            <option value="{{ $penyimpanan->id }}">{{ $penyimpanan->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="kategori-strategi">{{ __('Strategi pengambilan bawaan') }}</label>
                    <select class="form-select" id="kategori-strategi" wire:model="form.removal_strategy">
                        <option value="">{{ __('Ikuti induk atau FIFO') }}</option>
                        @foreach ($strategies as $nilai => $label)
                            <option value="{{ $nilai }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="kategori-toleransi-pct">{{ __('Toleransi (%)') }}</label>
                    <input class="form-control @error('form.tolerance_pct') is-invalid @enderror"
                           id="kategori-toleransi-pct" type="text" wire:model="form.tolerance_pct">
                    @error('form.tolerance_pct') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="kategori-toleransi-abs">{{ __('Toleransi mutlak') }}</label>
                    <input class="form-control @error('form.tolerance_abs') is-invalid @enderror"
                           id="kategori-toleransi-abs" type="text" wire:model="form.tolerance_abs">
                    @error('form.tolerance_abs') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalForm">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    @if ($deactivatingId)
        <div class="card border-danger mb-3">
            <div class="card-header text-danger"><strong>{{ __('Nonaktifkan kategori') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="kategori-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('reasonCode') is-invalid @enderror" id="kategori-alasan"
                            wire:model="reasonCode">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasan as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="kategori-keterangan">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="kategori-keterangan" type="text" wire:model="reasonNotes"
                           placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-danger" type="button" wire:click="nonaktifkan">{{ __('Nonaktifkan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalNonaktif">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Kategori') }}</th>
                        <th>{{ __('Penyimpanan') }}</th>
                        <th>{{ __('Strategi') }}</th>
                        <th>{{ __('Toleransi opname') }}</th>
                        <th class="text-end">{{ __('Item') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pohon as $baris)
                        @php($kategori = $baris['kategori'])
                        <tr wire:key="kategori-{{ $kategori->id }}">
                            <td>
                                <span style="padding-left: {{ $baris['level'] * 20 }}px">
                                    @if ($baris['level'] > 0)<i class="bi bi-arrow-return-right text-muted"></i>@endif
                                    <span class="fw-semibold">{{ $kategori->name }}</span>
                                </span>
                                <div class="small text-muted" style="padding-left: {{ $baris['level'] * 20 }}px">
                                    {{ $kategori->code }}
                                </div>
                            </td>
                            <td>{{ $kategori->storageCategory?->name ?? '—' }}</td>
                            <td>{{ $kategori->removal_strategy?->label() ?? '—' }}</td>
                            <td class="small">
                                {{ $kategori->tolerance_pct ? (float) $kategori->tolerance_pct.'%' : '—' }}
                                /
                                {{ $kategori->tolerance_abs ? (float) $kategori->tolerance_abs : '—' }}
                            </td>
                            <td class="text-end">{{ $kategori->items_count }}</td>
                            <td>
                                <span class="badge text-bg-{{ $kategori->is_active ? 'success' : 'secondary' }}">
                                    {{ $kategori->is_active ? __('Aktif') : __('Nonaktif') }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('create', \App\Domain\Master\Models\ItemCategory::class)
                                    <button class="btn btn-sm btn-outline-primary" type="button"
                                            wire:click="buat({{ $kategori->id }})">{{ __('Sub') }}</button>
                                @endcan
                                @can('update', $kategori)
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            wire:click="ubah({{ $kategori->id }})">{{ __('Ubah') }}</button>
                                @endcan
                                @if ($kategori->is_active)
                                    @can('deactivate', $kategori)
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                wire:click="mintaNonaktif({{ $kategori->id }})">
                                            {{ __('Nonaktifkan') }}
                                        </button>
                                    @endcan
                                @else
                                    @can('update', $kategori)
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="aktifkan({{ $kategori->id }})">{{ __('Aktifkan') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">{{ __('Belum ada kategori.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
