<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Berita acara waste') }}</h1>
            <p class="text-muted mb-0">{{ __('Menutup isi bin Waste: dibuang, dijual scrap, atau dipakai ulang. Stok baru bergerak saat BA ditutup dengan bukti.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('viewAny', App\Domain\Conversion\Models\Conversion::class)
                <a class="btn btn-outline-secondary" href="{{ route('conversions.index') }}">{{ __('Konversi material') }}</a>
            @endcan
            @can('create', App\Domain\Waste\Models\WasteDisposal::class)
                <a class="btn btn-primary" href="{{ route('waste-disposals.create') }}">{{ __('BA waste baru') }}</a>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-wst">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-wst" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor WST') }}">
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="filter-proyek-wst">{{ __('Proyek') }}</label>
                <select class="form-select" id="filter-proyek-wst" wire:model.live="projectFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-wst">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-wst" wire:model.live="statusFilter">
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
                        <th scope="col">{{ __('Gudang') }}</th>
                        <th scope="col">{{ __('Disposisi') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($disposals as $w)
                        <tr>
                            <td>
                                <a href="{{ route('waste-disposals.show', $w) }}">{{ $w->number }}</a>
                                <div class="small text-muted">{{ $w->submitter?->name }}</div>
                            </td>
                            <td>{{ $w->project?->code }} <div class="small text-muted">{{ $w->project?->name }}</div></td>
                            <td>{{ $w->warehouse?->code }}</td>
                            <td>{{ $w->disposition->label() }}</td>
                            <td class="text-end">{{ $w->lines_count }}</td>
                            <td><span class="badge {{ $w->status->badge() }}">{{ $w->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">{{ __('Belum ada berita acara waste.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($disposals->hasPages())
            <div class="card-footer">{{ $disposals->links() }}</div>
        @endif
    </div>
</div>
