<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Penyesuaian stok') }}</h1>
            <p class="text-muted mb-0">{{ __('Koreksi stok ± per bin dengan alasan. Manual selalu lewat approval; hasil opname disetujui di tingkat sesi.') }}</p>
        </div>
        @can('create', App\Domain\Adjustment\Models\StockAdjustment::class)
            <a class="btn btn-primary" href="{{ route('adjustments.create') }}">{{ __('Penyesuaian baru') }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-adj">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-adj" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor ADJ') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-adj">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-adj" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-asal-adj">{{ __('Asal') }}</label>
                <select class="form-select" id="filter-asal-adj" wire:model.live="originFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($origins as $nilai => $label)
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
                        <th scope="col">{{ __('Gudang') }}</th>
                        <th scope="col">{{ __('Asal') }}</th>
                        <th scope="col">{{ __('Alasan') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adjustments as $a)
                        <tr>
                            <td>
                                <a href="{{ route('adjustments.show', $a) }}">{{ $a->number }}</a>
                                <div class="small text-muted">{{ $a->submitter?->name }}</div>
                            </td>
                            <td>{{ $a->warehouse?->code }}</td>
                            <td>{{ $a->origin->label() }} @if ($a->stockCount) <div class="small text-muted">{{ $a->stockCount->number }}</div> @endif @if ($a->reversal_of_id) <span class="badge text-bg-light">{{ __('pembalik') }}</span> @endif</td>
                            <td>{{ $a->reason?->label }}</td>
                            <td class="text-end">{{ $a->lines_count }}</td>
                            <td><span class="badge {{ $a->status->badge() }}">{{ $a->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">{{ __('Belum ada penyesuaian stok.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($adjustments->hasPages())
            <div class="card-footer">{{ $adjustments->links() }}</div>
        @endif
    </div>
</div>
