<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Aset') }}</h1>
            <p class="text-muted mb-0">{{ __('Aset dipinjamkan per serial: posisi, proyek peminjam, jatuh tempo kembali, kondisi, dan sisa umur. State mengikuti lokasinya di kartu stok.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('asset.view')
                <a class="btn btn-outline-secondary" href="{{ route('asset-handovers.index') }}">{{ __('Serah terima aset') }}</a>
                <a class="btn btn-outline-secondary" href="{{ route('reports.show', 'aset-dipinjamkan') }}">{{ __('Laporan aset dipinjamkan') }}</a>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-aset">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-aset" type="search" data-scan wire:model.live.debounce.400ms="search" placeholder="{{ __('Serial atau item') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-state-aset">{{ __('State') }}</label>
                <select class="form-select" id="filter-state-aset" wire:model.live="stateFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($states as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-proyek-aset">{{ __('Proyek peminjam') }}</label>
                <select class="form-select" id="filter-proyek-aset" wire:model.live="projectFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <div class="form-check">
                    <input class="form-check-input" id="aset-lewat" type="checkbox" wire:model.live="attention">
                    <label class="form-check-label" for="aset-lewat">{{ __('Lewat jatuh tempo') }}</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Serial') }}</th>
                        <th scope="col">{{ __('State') }}</th>
                        <th scope="col">{{ __('Lokasi') }}</th>
                        <th scope="col">{{ __('Proyek') }}</th>
                        <th scope="col">{{ __('Kembali') }}</th>
                        <th scope="col">{{ __('Kondisi') }}</th>
                        <th class="text-end" scope="col">{{ __('Sisa umur') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assets as $a)
                        @php($sisa = $a->remainingLifePercent())
                        <tr>
                            <td>
                                <a href="{{ route('assets.show', $a) }}">{{ $a->serial_no }}</a>
                                <div class="small text-muted">{{ $a->item?->code }} — {{ $a->item?->name }}</div>
                            </td>
                            <td><span class="badge text-bg-{{ $a->stateBadge() }}">{{ $a->asset_state->label() }}</span></td>
                            <td class="small">{{ $lokasi->get($a->id)?->bin?->code ?? '—' }}</td>
                            <td>{{ $a->currentProject?->code ?? '—' }}</td>
                            <td class="small">
                                {{ $a->due_return_date?->format('d/m/Y') ?? '—' }}
                                @if ($a->isOverdue()) <span class="badge text-bg-danger">{{ __('Lewat') }}</span> @endif
                            </td>
                            <td class="small">{{ $a->condition_grade ?? '—' }} @if ($a->condition_score !== null) · {{ $a->condition_score }} % @endif</td>
                            <td class="text-end small">
                                @if ($sisa !== null)
                                    <span class="{{ $sisa < $ambang ? 'text-danger fw-semibold' : '' }}">{{ number_format($sisa, 1, ',', '.') }} %</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="7">{{ __('Belum ada aset.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($assets->hasPages())
            <div class="card-footer">{{ $assets->links() }}</div>
        @endif
    </div>
</div>
