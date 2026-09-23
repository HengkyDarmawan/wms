<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Surat jalan') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Apa yang dimuat, dibawa siapa, dan ke mana. Barang di perjalanan tetap tercatat milik gudang asal.') }}
            </p>
        </div>
        @can('create', App\Domain\Shipment\Models\Shipment::class)
            <a class="btn btn-primary" href="{{ route('shipments.create') }}">{{ __('Surat jalan baru') }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label" for="cari-sj">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-sj" type="search"
                       wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor SJ atau resi') }}">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-status-sj">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-sj" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-gudang-sj">{{ __('Gudang asal') }}</label>
                <select class="form-select" id="filter-gudang-sj" wire:model.live="warehouseFilter">
                    <option value="">{{ __('Semua gudang') }}</option>
                    @foreach ($warehouses as $gudang)
                        <option value="{{ $gudang->id }}">{{ $gudang->code }} — {{ $gudang->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-cara-sj">{{ __('Cara kirim') }}</label>
                <select class="form-select" id="filter-cara-sj" wire:model.live="methodFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($methods as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <div class="form-check">
                    <input class="form-check-input" id="filter-selisih-sj" type="checkbox"
                           wire:model.live="hanyaBerselisih">
                    <label class="form-check-label" for="filter-selisih-sj">{{ __('Masih berselisih') }}</label>
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
                        <th scope="col">{{ __('Asal') }}</th>
                        <th scope="col">{{ __('Tujuan') }}</th>
                        <th scope="col">{{ __('Dibawa') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shipments as $sj)
                        <tr @class(['table-warning' => $sj->open_discrepancies_count > 0])>
                            <td>
                                <a href="{{ route('shipments.show', $sj) }}">{{ $sj->number }}</a>
                                @if ($sj->open_discrepancies_count > 0)
                                    <div class="small text-danger">
                                        {{ __(':n selisih terbuka', ['n' => $sj->open_discrepancies_count]) }}
                                    </div>
                                @endif
                            </td>
                            <td>{{ $sj->warehouse?->code }}</td>
                            <td>
                                {{ $sj->destinationLabel() }}
                                <div class="small text-muted">{{ $destinations[$sj->destination_type->value] }}</div>
                            </td>
                            <td class="small">
                                {{ $sj->shipment_method->label() }}
                                <div class="text-muted">{{ $sj->carrierLabel() }}</div>
                            </td>
                            <td class="text-end">{{ $sj->lines_count }}</td>
                            <td>
                                <span class="badge {{ $sj->status->badge() }}">{{ $sj->status->label() }}</span>
                                @if ($sj->shipped_at)
                                    <div class="small text-muted">{{ $sj->shipped_at->format('d/m/Y H:i') }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">
                                {{ __('Belum ada surat jalan yang cocok dengan penyaring ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($shipments->hasPages())
            <div class="card-footer">{{ $shipments->links() }}</div>
        @endif
    </div>
</div>
