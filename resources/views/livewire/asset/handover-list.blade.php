<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Serah terima aset') }}</h1>
            <p class="text-muted mb-0">{{ __('Lahir otomatis saat surat jalan aset diterima proyek; kembali saat GRN retur diterima; selesai setelah aset diperiksa.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('assets.index') }}">{{ __('Daftar aset') }}</a>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-ast">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-ast" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor AST atau serial') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-proyek-ast">{{ __('Proyek') }}</label>
                <select class="form-select" id="filter-proyek-ast" wire:model.live="projectFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-ast">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-ast" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <div class="form-check">
                    <input class="form-check-input" id="ast-lewat" type="checkbox" wire:model.live="overdue">
                    <label class="form-check-label" for="ast-lewat">{{ __('Lewat jatuh tempo') }}</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Nomor') }}</th>
                        <th scope="col">{{ __('Aset') }}</th>
                        <th scope="col">{{ __('Proyek') }}</th>
                        <th scope="col">{{ __('Keluar') }}</th>
                        <th scope="col">{{ __('Jatuh tempo') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($handovers as $h)
                        <tr>
                            <td><a href="{{ route('asset-handovers.show', $h) }}">{{ $h->number }}</a> <div class="small text-muted">{{ $h->warehouse?->code }}</div></td>
                            <td>{{ $h->serial?->serial_no }} <div class="small text-muted">{{ $h->item?->code }}</div></td>
                            <td>{{ $h->project?->code }} <div class="small text-muted">{{ $h->project?->name }}</div></td>
                            <td class="small">{{ $h->checked_out_at?->lokal()->format('d/m/Y') }}</td>
                            <td class="small">
                                {{ $h->due_return_date?->format('d/m/Y') ?? '—' }}
                                @if ($h->isOverdue()) <span class="badge text-bg-danger">{{ __('Lewat') }}</span> @endif
                            </td>
                            <td>
                                <span class="badge {{ $h->status->badge() }}">{{ $h->status->label() }}</span>
                                @if ($h->lost_at) <span class="badge text-bg-danger">{{ __('Hilang') }}</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">{{ __('Belum ada serah terima aset.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($handovers->hasPages())
            <div class="card-footer">{{ $handovers->links() }}</div>
        @endif
    </div>
</div>
