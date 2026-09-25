<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Kejadian stok') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Outbox yang ditulis bersamaan dengan kartu stok. Layar baca saja: kejadian tidak pernah diubah.') }}
            </p>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ __('Belum terkirim') }}</div>
                    <div class="h4 mb-0">{{ number_format($ringkasan['belum'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 {{ $ringkasan['gagal'] > 0 ? 'border-danger' : '' }}">
                <div class="card-body">
                    <div class="text-muted small">{{ __('Gagal berulang') }}</div>
                    <div class="h4 mb-0 {{ $ringkasan['gagal'] > 0 ? 'text-danger' : '' }}">
                        {{ number_format($ringkasan['gagal'], 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ __('Total kejadian') }}</div>
                    <div class="h4 mb-0">{{ number_format($ringkasan['total'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-lg-4">
                <label class="form-label" for="filter-jenis-kejadian">{{ __('Jenis kejadian') }}</label>
                <select class="form-select" id="filter-jenis-kejadian" wire:model.live="typeFilter">
                    <option value="">{{ __('Semua jenis') }}</option>
                    @foreach ($types as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-status-kejadian">{{ __('Status kirim') }}</label>
                <select class="form-select" id="filter-status-kejadian" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    <option value="belum">{{ __('Belum terkirim') }}</option>
                    <option value="gagal">{{ __('Gagal berulang') }}</option>
                    <option value="terkirim">{{ __('Terkirim') }}</option>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="kejadian-dari">{{ __('Dari tanggal') }}</label>
                <input class="form-control" id="kejadian-dari" type="date" wire:model.live="dariTanggal">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="kejadian-sampai">{{ __('Sampai tanggal') }}</label>
                <input class="form-control" id="kejadian-sampai" type="date" wire:model.live="sampaiTanggal">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Waktu') }}</th>
                        <th scope="col">{{ __('Jenis') }}</th>
                        <th scope="col">{{ __('Sumber') }}</th>
                        <th scope="col">{{ __('Proyek') }}</th>
                        <th scope="col">{{ __('Status kirim') }}</th>
                        <th scope="col">{{ __('Galat terakhir') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($kejadian as $k)
                        <tr @class(['table-danger' => ! $k->isPublished() && $k->attempts >= 3])>
                            <td class="text-nowrap">{{ $k->occurred_at?->lokal()->format('d/m/Y H:i') }}</td>
                            <td>{{ $k->event_type->label() }}</td>
                            <td class="small">
                                {{ $k->source_type ?? '—' }}
                                @if ($k->source_id)
                                    <div class="text-muted">#{{ $k->source_id }}</div>
                                @endif
                            </td>
                            <td>{{ $k->project?->code ?? '—' }}</td>
                            <td>
                                @if ($k->isPublished())
                                    <span class="badge text-bg-success">{{ __('Terkirim') }}</span>
                                    <div class="small text-muted">{{ $k->published_at?->lokal()->format('d/m/Y H:i') }}</div>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Belum terkirim') }}</span>
                                    @if ($k->attempts > 0)
                                        <div class="small text-muted">{{ __(':n percobaan', ['n' => $k->attempts]) }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="small text-muted">{{ $k->last_error ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">
                                {{ __('Belum ada kejadian stok yang cocok dengan penyaring ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($kejadian->hasPages())
            <div class="card-footer">{{ $kejadian->links() }}</div>
        @endif
    </div>
</div>
