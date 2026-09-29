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
                <x-pilih model="warehouseFilter" id="filter-gudang-stok" live :label="__('Gudang')" :kosong="__('Semua gudang')"
                         :options="$warehouses->map(fn ($g) => ['value' => $g->id, 'text' => $g->code.' — '.$g->name])->all()" />
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
                        <th scope="col">{{ __('Lokasi') }}</th>
                        <th class="text-end" scope="col">{{ __('Tersedia') }}</th>
                        <th class="text-end" scope="col">{{ __('Dicadangkan') }}</th>
                        <th class="text-end" scope="col">{{ __('Karantina') }}</th>
                        <th class="text-end" scope="col">{{ __('Rusak') }}</th>
                        @if ($tampilPotongan)
                            <th class="text-end" scope="col">{{ __('Potongan') }}</th>
                        @endif
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
                            <td class="small">
                                {{-- K-M (A-382): maks. 2 bin (terbanyak), sisanya "+n bin lain" → Kartu stok. --}}
                                @php $lok = $lokasi[$b->item_id.':'.$b->warehouse_id] ?? null; @endphp
                                @if ($lok)
                                    @foreach ($lok['bins'] as $lb)
                                        <a class="d-block font-monospace text-nowrap" title="{{ $lb['kode'] }}"
                                           href="{{ route('stock.card', ['item' => $b->item_id, 'warehouseFilter' => $b->warehouse_id, 'binFilter' => $lb['id']]) }}">{{ $lb['pendek'] }}</a>
                                    @endforeach
                                    @if ($lok['total'] > 2)
                                        <a class="text-muted" href="{{ route('stock.card', ['item' => $b->item_id, 'warehouseFilter' => $b->warehouse_id]) }}">{{ __('+:n bin lain', ['n' => $lok['total'] - 2]) }}</a>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            @php($itemBaris = $itemHalaman[$b->item_id] ?? null)
                            <td class="text-end {{ $tersedia < 0 ? 'text-danger fw-semibold' : '' }}">
                                {{ \App\Domain\Master\Support\QtyFormat::withUnit($tersedia, $itemBaris?->baseUom?->code) }}
                                @if ($kemasan = \App\Domain\Master\Support\QtyFormat::packaging($itemBaris, $tersedia))
                                    <div class="small text-muted">{{ $kemasan }}</div>
                                @endif
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
                            @if ($tampilPotongan)
                                <td class="text-end">
                                    @if ($this->pakaiPotongan((string) $b->tracking_mode))
                                        {{ number_format((int) $b->piece_count, 0, ',', '.') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="{{ $tampilPotongan ? 8 : 7 }}">
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
