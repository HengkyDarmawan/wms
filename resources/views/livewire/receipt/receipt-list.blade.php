<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Penerimaan barang') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Barang masuk dari vendor atau transfer antar gudang. Stok baru tercatat saat GRN diterima.') }}
            </p>
        </div>
        @can('create', App\Domain\Receipt\Models\GoodsReceipt::class)
            <a class="btn btn-primary" href="{{ route('receipts.create') }}">{{ __('Penerimaan baru') }}</a>
        @endcan
    </div>

    @if ($incoming->isNotEmpty())
        <div class="card border-info mb-3">
            <div class="card-header"><strong>{{ __('Transfer masuk menunggu diterima') }}</strong></div>
            <ul class="list-group list-group-flush">
                @foreach ($incoming as $sj)
                    <li class="list-group-item d-flex justify-content-between align-items-center" wire:key="sj-masuk-{{ $sj->id }}">
                        <span>
                            <strong>{{ $sj->number }}</strong>
                            <span class="text-muted small">{{ $sj->warehouse?->code }} → {{ $sj->destinationWarehouse?->code }}</span>
                        </span>
                        @can('create', App\Domain\Receipt\Models\GoodsReceipt::class)
                            <a class="btn btn-sm btn-outline-primary"
                               href="{{ route('receipts.create', ['shipment' => $sj->id]) }}">{{ __('Terima') }}</a>
                        @endcan
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label" for="cari-grn">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-grn" type="search" wire:model.live.debounce.400ms="search"
                       placeholder="{{ __('Nomor GRN atau surat jalan vendor') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-grn">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-grn" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-sumber-grn">{{ __('Sumber') }}</label>
                <select class="form-select" id="filter-sumber-grn" wire:model.live="typeFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($types as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-gudang-grn">{{ __('Gudang') }}</label>
                <select class="form-select" id="filter-gudang-grn" wire:model.live="warehouseFilter">
                    <option value="">{{ __('Semua gudang') }}</option>
                    @foreach ($warehouses as $gudang)
                        <option value="{{ $gudang->id }}">{{ $gudang->code }} — {{ $gudang->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Nomor') }}</th>
                        <th scope="col">{{ __('Gudang') }}</th>
                        <th scope="col">{{ __('Sumber') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $grn)
                        <tr>
                            <td><a href="{{ route('receipts.show', $grn) }}">{{ $grn->number }}</a></td>
                            <td>{{ $grn->warehouse?->code }}</td>
                            <td>
                                {{ $grn->sourceLabel() }}
                                <div class="small text-muted">{{ $grn->receipt_type->label() }}</div>
                            </td>
                            <td class="text-end">{{ $grn->lines_count }}</td>
                            <td>
                                <span class="badge {{ $grn->status->badge() }}">{{ $grn->status->label() }}</span>
                                @if ($grn->received_at)
                                    <div class="small text-muted">{{ $grn->received_at->lokal()->format('d/m/Y H:i') }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="5">
                                {{ __('Belum ada penerimaan yang cocok dengan penyaring ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($receipts->hasPages())
            <div class="card-footer">{{ $receipts->links() }}</div>
        @endif
    </div>
</div>
