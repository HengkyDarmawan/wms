<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Retur ke vendor') }}</h1>
            <p class="text-muted mb-0">{{ __('Barang dari Karantina yang dikembalikan ke vendor. Stok keluar saat RTV dikirim.') }}</p>
        </div>
        @can('create', App\Domain\Receipt\Models\VendorReturn::class)
            <a class="btn btn-primary" href="{{ route('vendor-returns.create') }}">{{ __('RTV baru') }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-rtv">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-rtv" type="search" wire:model.live.debounce.400ms="search"
                       placeholder="{{ __('Nomor RTV') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-rtv">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-rtv" wire:model.live="statusFilter">
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
                        <th scope="col">{{ __('GRN') }}</th>
                        <th scope="col">{{ __('Gudang') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($returns as $r)
                        <tr>
                            <td><a href="{{ route('vendor-returns.show', $r) }}">{{ $r->number }}</a></td>
                            <td>{{ $r->vendor?->name }}</td>
                            <td>{{ $r->receipt?->number }}</td>
                            <td>{{ $r->warehouse?->code }}</td>
                            <td class="text-end">{{ $r->lines_count }}</td>
                            <td><span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">{{ __('Belum ada retur ke vendor.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($returns->hasPages())
            <div class="card-footer">{{ $returns->links() }}</div>
        @endif
    </div>
</div>
