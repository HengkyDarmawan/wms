<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Pemakaian material') }}</h1>
            <p class="text-muted mb-0">{{ __('Barang habis pakai di Gudang Site yang dipakai proyek. Setelah dikonfirmasi, barang keluar dari stok dan tercatat sebagai Terpakai.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('issue.view')
                <a class="btn btn-outline-secondary" href="{{ route('reports.show', 'material-per-proyek') }}">{{ __('Material per proyek') }}</a>
            @endcan
            @can('create', App\Domain\Issue\Models\MaterialIssue::class)
                <a class="btn btn-primary" href="{{ route('issues.create') }}">{{ __('Pemakaian baru') }}</a>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-isu">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-isu" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor ISU') }}">
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="filter-proyek-isu">{{ __('Proyek') }}</label>
                <select class="form-select" id="filter-proyek-isu" wire:model.live="projectFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-isu">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-isu" wire:model.live="statusFilter">
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
                        <th scope="col">{{ __('Gudang Site') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($issues as $i)
                        <tr>
                            <td>
                                <a href="{{ route('issues.show', $i) }}">{{ $i->number }}</a>
                                <div class="small text-muted">
                                    {{ $i->issuer?->name }}
                                    @if ($i->reversalOf) · {{ __('Pembalik') }} {{ $i->reversalOf->number }} @endif
                                </div>
                            </td>
                            <td>{{ $i->project?->code }} <div class="small text-muted">{{ $i->project?->name }}</div></td>
                            <td>{{ $i->warehouse?->code }}</td>
                            <td class="text-end">{{ $i->lines_count }}</td>
                            <td><span class="badge {{ $i->status->badge() }}">{{ $i->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="5">{{ __('Belum ada pemakaian material.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($issues->hasPages())
            <div class="card-footer">{{ $issues->links() }}</div>
        @endif
    </div>
</div>
