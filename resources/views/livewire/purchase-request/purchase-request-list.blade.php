<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Purchase Request') }}</h1>
            <p class="text-muted mb-0">{{ __('Permintaan pembelian ke tim Purchasing: dari backorder REQ, titik pesan ulang, atau manual. Tanpa harga — PO dan nilai ada di sistem Purchasing.') }}</p>
        </div>
        @can('create', App\Domain\PurchaseRequest\Models\PurchaseRequest::class)
            <a class="btn btn-primary" href="{{ route('purchase-requests.create') }}">{{ __('PRQ manual') }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label" for="cari-prq">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-prq" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor PRQ / PO / pesanan') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-gudang-prq">{{ __('Gudang tujuan') }}</label>
                <select class="form-select" id="filter-gudang-prq" wire:model.live="warehouseFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-asal-prq">{{ __('Asal') }}</label>
                <select class="form-select" id="filter-asal-prq" wire:model.live="originFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($origins as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-prq">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-prq" wire:model.live="statusFilter">
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
                        <th scope="col">{{ __('Gudang tujuan') }}</th>
                        <th scope="col">{{ __('Asal') }}</th>
                        <th scope="col">{{ __('Proyek') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th class="text-end" scope="col">{{ __('Catatan pemesanan') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $p)
                        <tr>
                            <td>
                                <a href="{{ route('purchase-requests.show', $p) }}">{{ $p->number }}</a>
                                <div class="small text-muted">{{ $p->creator?->name ?? __('Sistem') }} · {{ $p->created_at?->lokal()->format('d/m/Y') }}</div>
                            </td>
                            <td>{{ $p->warehouse?->code }}</td>
                            <td>
                                {{ $p->origin->label() }}
                                @if ($p->materialRequest) <div class="small text-muted">{{ $p->materialRequest->number }}</div> @endif
                            </td>
                            <td>{{ $p->project?->code ?? '—' }}</td>
                            <td class="text-end">{{ $p->lines_count }}</td>
                            <td class="text-end">{{ $p->orders_count }}</td>
                            <td><span class="badge {{ $p->status->badge() }}">{{ $p->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="7">{{ __('Belum ada Purchase Request.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
