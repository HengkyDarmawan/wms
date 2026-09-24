<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Retur dari proyek') }}</h1>
            <p class="text-muted mb-0">{{ __('Barang kembali dari proyek ke gudang: diterima di bin Retur, lalu dipilah layak / rusak / offcut / waste.') }}</p>
        </div>
        @can('create', App\Domain\Return\Models\GoodsReturn::class)
            <a class="btn btn-primary" href="{{ route($rute.'.create') }}">{{ __('Retur baru') }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-ret">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-ret" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor RET') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-ret">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-ret" wire:model.live="statusFilter">
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
                        <th scope="col">{{ __('Proyek') }}</th>
                        <th scope="col">{{ __('Ke gudang') }}</th>
                        <th scope="col">{{ __('Pengangkutan') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($returns as $r)
                        <tr>
                            <td>
                                <a href="{{ route($rute.'.show', $r) }}">{{ $r->number }}</a>
                                <div class="small text-muted">{{ $r->requester?->name }}</div>
                            </td>
                            <td>{{ $r->project?->code }} <div class="small text-muted">{{ $r->project?->name }}</div></td>
                            <td>{{ $r->toWarehouse?->code }}</td>
                            <td>{{ $r->self_delivered ? __('Diantar sendiri') : __('SJ balik dari').' '.$r->fromWarehouse?->code }}</td>
                            <td class="text-end">{{ $r->requested_lines_count }}</td>
                            <td><span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">{{ __('Belum ada retur.') }}</td>
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
