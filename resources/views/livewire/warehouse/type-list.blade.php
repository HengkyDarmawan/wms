<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Tipe gudang') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Tipe bawaan boleh diganti namanya, tetapi kodenya dikunci karena dipakai aturan Gudang Site.') }}
            </p>
        </div>

        @can('create', \App\Domain\Warehouse\Models\WarehouseType::class)
            <button class="btn btn-primary" type="button" wire:click="buat">
                <i class="bi bi-plus-lg"></i> {{ __('Tambah tipe') }}
            </button>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    @if ($showForm)
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ $editingId ? __('Ubah tipe gudang') : __('Tipe gudang baru') }}</strong>
                <span class="text-muted small ms-2">{{ __('Field bertanda * wajib diisi.') }}</span>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="tipe-kode">{{ __('Kode tipe') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.code') is-invalid @enderror" id="tipe-kode"
                           type="text" wire:model="form.code" @disabled($editingId !== null)>
                    @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="tipe-nama">{{ __('Nama tipe') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.name') is-invalid @enderror" id="tipe-nama"
                           type="text" wire:model="form.name">
                    @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalForm">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Tipe') }}</th>
                        <th>{{ __('Sifat') }}</th>
                        <th class="text-end">{{ __('Gudang') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr wire:key="tipe-{{ $type->id }}">
                            <td>
                                <div class="fw-semibold">{{ $type->name }}</div>
                                <div class="small text-muted">{{ $type->code }}</div>
                            </td>
                            <td>
                                @if ($type->is_builtin)
                                    <span class="badge text-bg-info">{{ __('Bawaan') }}</span>
                                @else
                                    <span class="badge text-bg-light">{{ __('Buatan company') }}</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $type->warehouses_count }}</td>
                            <td>
                                <span class="badge text-bg-{{ $type->is_active ? 'success' : 'secondary' }}">
                                    {{ $type->is_active ? __('Aktif') : __('Nonaktif') }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('update', $type)
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            wire:click="ubah({{ $type->id }})">{{ __('Ubah') }}</button>
                                @endcan
                                @if ($type->is_active)
                                    @can('deactivate', $type)
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                wire:click="nonaktifkan({{ $type->id }})">{{ __('Nonaktifkan') }}</button>
                                    @endcan
                                @else
                                    @can('update', $type)
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="aktifkan({{ $type->id }})">{{ __('Aktifkan') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">{{ __('Belum ada tipe gudang.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
