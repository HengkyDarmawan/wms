<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Transfer') }}</h1>
            <p class="text-muted mb-0">{{ __('Pemindahan stok antar gudang, antar proyek, dan antar titik dalam satu proyek. Barang bergerak lewat tugas picking, surat jalan, dan penerimaan.') }}</p>
        </div>
        @can('create', App\Domain\Transfer\Models\Transfer::class)
            <a class="btn btn-primary" href="{{ route('transfers.create') }}">{{ __('Transfer baru') }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-trf">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-trf" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor TRF') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-trf">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-trf" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-asal-trf">{{ __('Asal dokumen') }}</label>
                <select class="form-select" id="filter-asal-trf" wire:model.live="originFilter">
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
                        <th scope="col">{{ __('Gudang asal → tujuan') }}</th>
                        <th scope="col">{{ __('Jenis') }}</th>
                        <th scope="col">{{ __('Asal dokumen') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transfers as $t)
                        <tr>
                            <td>
                                <a href="{{ route('transfers.show', $t) }}">{{ $t->number }}</a>
                                <div class="small text-muted">{{ $t->submitter?->name ?? __('Sistem') }}</div>
                            </td>
                            <td>{{ $t->fromWarehouse?->code }} → {{ $t->toWarehouse?->code }}</td>
                            <td>{{ $t->kind()->label() }}</td>
                            <td>{{ $t->origin->label() }}</td>
                            <td class="text-end">{{ $t->lines_count }}</td>
                            <td><span class="badge {{ $t->status->badge() }}">{{ $t->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">{{ __('Belum ada transfer.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transfers->hasPages())
            <div class="card-footer">{{ $transfers->links() }}</div>
        @endif
    </div>
</div>
