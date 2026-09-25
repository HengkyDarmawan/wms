<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Purchase Order') }}</h1>
            <p class="text-muted mb-0">{{ __('PO ke vendor dari Purchase Request yang disetujui, lengkap dengan harga beli. PO yang disetujui otomatis menjadi catatan pemesanan di gudang.') }}</p>
        </div>
        @can('create', App\Domain\Purchasing\Models\PurchaseOrder::class)
            <a class="btn btn-primary" href="{{ route('purchase-orders.create') }}"><i class="bi bi-plus-lg"></i> {{ __('PO baru') }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label" for="cari-po">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-po" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor PO / PRQ / vendor') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-vendor-po">{{ __('Vendor') }}</label>
                <select class="form-select" id="filter-vendor-po" wire:model.live="vendorFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-gudang-po">{{ __('Gudang tujuan') }}</label>
                <select class="form-select" id="filter-gudang-po" wire:model.live="warehouseFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-po">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-po" wire:model.live="statusFilter">
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
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Nomor') }}</th>
                        <th scope="col">{{ __('Vendor') }}</th>
                        <th scope="col">{{ __('Gudang tujuan') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th class="text-end" scope="col">{{ __('Nilai PO') }}</th>
                        <th scope="col">{{ __('ETA') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $po)
                        <tr wire:key="po-{{ $po->id }}">
                            <td>
                                <a href="{{ route('purchase-orders.show', $po) }}">{{ $po->number }}</a>
                                <div class="small text-muted">{{ $po->creator?->name }} · {{ $po->order_date?->format('d/m/Y') }}</div>
                            </td>
                            <td>{{ $po->vendor?->name }} <div class="small text-muted">{{ $po->vendor?->code }}</div></td>
                            <td>{{ $po->warehouse?->code }}</td>
                            <td class="text-end">{{ $po->lines_count }}</td>
                            <td class="text-end text-nowrap">{{ \App\Domain\Purchasing\Support\Money::format($po->total_amount) }}</td>
                            <td>{{ $po->eta_date?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="badge {{ $po->status->badge() }}">{{ $po->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="7">{{ __('Belum ada Purchase Order.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="card-footer">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
