<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Konversi material') }}</h1>
            <p class="text-muted mb-0">{{ __('Potong, rakit, bongkar, atau ganti kemasan di satu gudang. Input keluar dari stok; output dan offcut masuk sebagai potongan baru bersilsilah, waste ke bin Waste.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('viewAny', App\Domain\Waste\Models\WasteDisposal::class)
                <a class="btn btn-outline-secondary" href="{{ route('waste-disposals.index') }}">{{ __('Berita acara waste') }}</a>
            @endcan
            @can('create', App\Domain\Conversion\Models\Conversion::class)
                <a class="btn btn-primary" href="{{ route('conversions.create') }}">{{ __('Konversi baru') }}</a>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-cnv">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-cnv" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor CNV') }}">
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="filter-proyek-cnv">{{ __('Proyek') }}</label>
                <select class="form-select" id="filter-proyek-cnv" wire:model.live="projectFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-cnv">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-cnv" wire:model.live="statusFilter">
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
                        <th scope="col">{{ __('Jenis') }}</th>
                        <th class="text-end" scope="col">{{ __('Input') }}</th>
                        <th class="text-end" scope="col">{{ __('Waste') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($conversions as $c)
                        <tr>
                            <td>
                                <a href="{{ route('conversions.show', $c) }}">{{ $c->number }}</a>
                                <div class="small text-muted">
                                    {{ $c->preparer?->name }}
                                    @if ($c->reversalOf) · {{ __('Pembalik') }} {{ $c->reversalOf->number }} @endif
                                </div>
                            </td>
                            <td>{{ $c->project?->code }} <div class="small text-muted">{{ $c->project?->name }}</div></td>
                            <td>{{ $c->warehouse?->code }}</td>
                            <td>{{ $types[$c->conversion_type->value] ?? $c->conversion_type->value }}</td>
                            <td class="text-end">{{ number_format((float) $c->total_input, 2, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $c->total_waste, 2, ',', '.') }}</td>
                            <td><span class="badge {{ $c->status->badge() }}">{{ $c->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="7">{{ __('Belum ada konversi material.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($conversions->hasPages())
            <div class="card-footer">{{ $conversions->links() }}</div>
        @endif
    </div>
</div>
