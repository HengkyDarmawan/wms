<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Saldo stok') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Jumlah per item dan gudang. Kolom Tersedia sudah dikurangi reservasi aktif.') }}
            </p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-5">
                <label class="form-label" for="cari-stok">{{ __('Cari item') }}</label>
                <input class="form-control" id="cari-stok" type="search" data-scan
                       wire:model.live.debounce.400ms="search" placeholder="{{ __('Kode, nama, atau barcode item') }}">
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="filter-gudang-stok">{{ __('Gudang') }}</label>
                <select class="form-select" id="filter-gudang-stok" wire:model.live="warehouseFilter">
                    <option value="">{{ __('Semua gudang') }}</option>
                    @foreach ($warehouses as $gudang)
                        <option value="{{ $gudang->id }}">{{ $gudang->code }} — {{ $gudang->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <div class="form-check">
                    <input class="form-check-input" id="tampil-nol" type="checkbox" wire:model.live="tampilkanNol">
                    <label class="form-check-label" for="tampil-nol">{{ __('Tampilkan saldo nol') }}</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Gudang') }}</th>
                        <th class="text-end" scope="col">{{ __('Tersedia') }}</th>
                        <th class="text-end" scope="col">{{ __('Dicadangkan') }}</th>
                        <th class="text-end" scope="col">{{ __('Karantina') }}</th>
                        <th class="text-end" scope="col">{{ __('Rusak') }}</th>
                        <th class="text-end" scope="col">{{ __('Potongan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $b)
                        @php
                            $dicadangkan = $reservasi[$b->item_id.':'.$b->warehouse_id] ?? 0;
                            $tersedia = (float) $b->qty_available - $dicadangkan;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('stock.card', $b->item_id) }}">{{ $b->item_code }}</a>
                                <div class="small text-muted">{{ $b->item_name }}</div>
                            </td>
                            <td>
                                {{ $b->warehouse_code }}
                                <div class="small text-muted">{{ $b->warehouse_name }}</div>
                            </td>
                            <td class="text-end {{ $tersedia < 0 ? 'text-danger fw-semibold' : '' }}">
                                {{ number_format($tersedia, 2, ',', '.') }}
                            </td>
                            <td class="text-end">
                                @if ($dicadangkan > 0)
                                    <span class="badge text-bg-warning">{{ number_format($dicadangkan, 2, ',', '.') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $b->qty_quarantine, 2, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $b->qty_damaged, 2, ',', '.') }}</td>
                            <td class="text-end">
                                @if ($this->pakaiPotongan((string) $b->tracking_mode))
                                    {{ number_format((int) $b->piece_count, 0, ',', '.') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="7">
                                {{ __('Belum ada saldo stok yang cocok dengan penyaring ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($baris->hasPages())
            <div class="card-footer">{{ $baris->links() }}</div>
        @endif
    </div>
</div>
