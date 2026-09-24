<div>
    <div class="mb-3">
        <h1 class="h3 mb-1">{{ __('Tugas put-away') }}</h1>
        <p class="text-muted mb-0">{{ __('Barang di bin Penerimaan yang menunggu ditaruh di bin penyimpanan.') }}</p>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-put">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-put" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="filter-gudang-put">{{ __('Gudang') }}</label>
                <select class="form-select" id="filter-gudang-put" wire:model.live="warehouseFilter">
                    <option value="">{{ __('Semua gudang') }}</option>
                    @foreach ($warehouses as $g)
                        <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</option>
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
                        <th scope="col">{{ __('GRN') }}</th>
                        <th scope="col">{{ __('Gudang') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tasks as $t)
                        <tr>
                            <td><a href="{{ route('putaways.show', $t) }}">{{ $t->number }}</a></td>
                            <td>{{ $t->receipt?->number }}</td>
                            <td>{{ $t->warehouse?->code }}</td>
                            <td class="text-end">{{ $t->lines_count }}</td>
                            <td><span class="badge {{ $t->status->badge() }}">{{ $t->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="5">{{ __('Tidak ada tugas put-away.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tasks->hasPages())
            <div class="card-footer">{{ $tasks->links() }}</div>
        @endif
    </div>
</div>
