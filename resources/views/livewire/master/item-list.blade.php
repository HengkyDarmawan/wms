<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Item') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Master barang beserta mode pelacakan, kepemilikan, dan satuan dasarnya.') }}
            </p>
        </div>

        @can('create', \App\Domain\Master\Models\Item::class)
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary" href="{{ route('imports.index') }}">
                    <i class="bi bi-file-earmark-spreadsheet"></i> {{ __('Impor Excel') }}
                </a>
                <a class="btn btn-primary" href="{{ route('items.create') }}">
                    <i class="bi bi-plus-lg"></i> {{ __('Tambah item') }}
                </a>
            </div>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    @if ($deactivatingId)
        <div class="card border-danger mb-3">
            <div class="card-header text-danger"><strong>{{ __('Nonaktifkan item') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="item-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('reasonCode') is-invalid @enderror" id="item-alasan"
                            wire:model="reasonCode">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasan as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="item-keterangan">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="item-keterangan" type="text" wire:model="reasonNotes"
                           placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-danger" type="button" wire:click="nonaktifkan">{{ __('Nonaktifkan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalNonaktif">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-lg-4">
                <label class="form-label" for="cari-item">{{ __('Cari item') }}</label>
                <input class="form-control" id="cari-item" type="search"
                       wire:model.live.debounce.400ms="search" data-scan placeholder="{{ __('Nama, kode, atau barcode…') }}">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-kategori-item">{{ __('Kategori') }}</label>
                <select class="form-select" id="filter-kategori-item" wire:model.live="categoryFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-pelacakan">{{ __('Pelacakan') }}</label>
                <select class="form-select" id="filter-pelacakan" wire:model.live="trackingFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($trackingModes as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-kepemilikan">{{ __('Kepemilikan') }}</label>
                <select class="form-select" id="filter-kepemilikan" wire:model.live="ownershipFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($ownerships as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-status-item">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-item" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Kategori') }}</th>
                        <th>{{ __('Satuan dasar') }}</th>
                        <th>{{ __('Pelacakan') }}</th>
                        <th>{{ __('Kepemilikan') }}</th>
                        <th class="text-end">{{ __('Titik pesan ulang') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr wire:key="item-{{ $item->id }}">
                            <td>
                                <a class="fw-semibold text-decoration-none" href="{{ route('items.show', $item) }}">
                                    {{ $item->name }}
                                </a>
                                <div class="small text-muted">{{ $item->code }}</div>
                            </td>
                            <td>{{ $item->category?->name ?? '—' }}</td>
                            <td>{{ $item->baseUom?->code ?? '—' }}</td>
                            <td>
                                {{ $item->tracking_mode->label() }}
                                @if ($item->has_expiry)
                                    <span class="badge text-bg-light">{{ __('Kedaluwarsa') }}</span>
                                @endif
                            </td>
                            <td>{{ $item->ownership_model->label() }}</td>
                            <td class="text-end">{{ $item->reorder_point ? (float) $item->reorder_point : '—' }}</td>
                            <td>
                                <span class="badge text-bg-{{ $item->statusBadge() }}">
                                    {{ $item->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('update', $item)
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('items.edit', $item) }}">
                                        {{ __('Ubah') }}
                                    </a>
                                @endcan

                                @if ($item->status->value === 'inactive')
                                    @can('update', $item)
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="aktifkan({{ $item->id }})">{{ __('Aktifkan') }}</button>
                                    @endcan
                                @elseif ($item->status->value === 'provisional')
                                    @can('update', $item)
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="aktifkan({{ $item->id }})">{{ __('Resmikan') }}</button>
                                    @endcan
                                @else
                                    @can('deactivate', $item)
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                wire:click="mintaNonaktif({{ $item->id }})">
                                            {{ __('Nonaktifkan') }}
                                        </button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">{{ __('Belum ada item yang cocok.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>
</div>
