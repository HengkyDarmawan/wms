<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Stock opname') }}</h1>
            <p class="text-muted mb-0">{{ __('Sesi hitung buta per gudang/zona/bin. Selisih disetujui di tingkat sesi lalu diposting sebagai penyesuaian stok.') }}</p>
        </div>
        <div class="d-flex gap-2">
            @can('viewAny', App\Domain\Count\Models\CountAssignment::class)
                <a class="btn btn-outline-primary" href="{{ route('count-tasks.index') }}">{{ __('Hitungan saya') }}</a>
            @endcan
            @can('create', App\Domain\Count\Models\StockCount::class)
                <a class="btn btn-primary" href="{{ route('counts.create') }}">{{ __('Sesi baru') }}</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <div class="text-muted small">{{ __('Sedang dihitung') }}</div>
                <div class="h4 mb-0">{{ $ringkasan['berjalan'] }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <div class="text-muted small">{{ __('Menunggu rekonsiliasi/approval') }}</div>
                <div class="h4 mb-0">{{ $ringkasan['rekonsiliasi'] }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <div class="text-muted small">{{ __('Ditutup') }}</div>
                <div class="h4 mb-0">{{ $ringkasan['ditutup'] }}</div>
            </div></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-opn">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-opn" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor OPN') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-opn">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-opn" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-jenis-opn">{{ __('Jenis') }}</label>
                <select class="form-select" id="filter-jenis-opn" wire:model.live="typeFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($types as $nilai => $label)
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
                        <th scope="col">{{ __('Jenis') }}</th>
                        <th scope="col">{{ __('Gudang') }}</th>
                        <th class="text-end" scope="col">{{ __('Bin') }}</th>
                        <th class="text-end" scope="col">{{ __('Akurasi') }}</th>
                        <th class="text-end" scope="col">{{ __('Selisih besar') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($counts as $c)
                        <tr>
                            <td>
                                <a href="{{ route('counts.show', $c) }}">{{ $c->number }}</a>
                                <div class="small text-muted">{{ $c->creator?->name }} @if ($c->is_audit) · {{ __('audit') }} @endif</div>
                            </td>
                            <td>{{ $c->count_type->label() }} @unless ($c->freeze_bins) <span class="badge text-bg-light">{{ __('tanpa beku') }}</span> @endunless</td>
                            <td>{{ $c->warehouses->pluck('code')->implode(', ') }}</td>
                            <td class="text-end">{{ $c->assignments_count ?: '—' }}</td>
                            <td class="text-end">{{ isset($akurasi[$c->id]['akurasi']) ? number_format($akurasi[$c->id]['akurasi'], 1, ',', '.').' %' : '—' }}</td>
                            <td class="text-end">{{ $akurasi[$c->id]['besar'] ?? '—' }}</td>
                            <td><span class="badge {{ $c->status->badge() }}">{{ $c->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="7">{{ __('Belum ada sesi opname.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($counts->hasPages())
            <div class="card-footer">{{ $counts->links() }}</div>
        @endif
    </div>
</div>
