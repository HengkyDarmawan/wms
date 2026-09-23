<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Kartu stok') }} — {{ $item->code }}</h1>
            <p class="text-muted mb-0">
                {{ $item->name }} · {{ __('Satuan dasar') }}: {{ $item->baseUom?->code ?? '—' }}
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('stock.index') }}">{{ __('Kembali ke saldo') }}</a>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-lg-3">
                <label class="form-label" for="kartu-gudang">{{ __('Gudang') }}</label>
                <select class="form-select" id="kartu-gudang" wire:model.live="warehouseFilter">
                    <option value="">{{ __('Semua gudang') }}</option>
                    @foreach ($warehouses as $gudang)
                        <option value="{{ $gudang->id }}">{{ $gudang->code }} — {{ $gudang->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="kartu-bin">{{ __('Bin') }}</label>
                <select class="form-select" id="kartu-bin" wire:model.live="binFilter">
                    <option value="">{{ __('Semua bin') }}</option>
                    @foreach ($bins as $bin)
                        <option value="{{ $bin->id }}">{{ $bin->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="kartu-dari">{{ __('Dari tanggal') }}</label>
                <input class="form-control" id="kartu-dari" type="date" wire:model.live="dariTanggal">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="kartu-sampai">{{ __('Sampai tanggal') }}</label>
                <input class="form-control" id="kartu-sampai" type="date" wire:model.live="sampaiTanggal">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Saldo per bin') }}</strong></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Gudang') }}</th>
                        <th scope="col">{{ __('Bin') }}</th>
                        <th scope="col">{{ __('Lot / Serial') }}</th>
                        <th scope="col">{{ __('Kondisi') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th class="text-end" scope="col">{{ __('Potongan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($saldo as $s)
                        <tr>
                            <td>{{ $s->bin?->warehouse?->code ?? '—' }}</td>
                            <td>{{ $s->bin?->code ?? '—' }}</td>
                            <td>{{ $s->serial?->serial_no ?? $s->lot?->lot_no ?? '—' }}</td>
                            <td><span class="badge text-bg-light">{{ $s->stock_status->label() }}</span></td>
                            <td class="text-end">{{ number_format((float) $s->qty_base, 2, ',', '.') }}</td>
                            <td class="text-end">
                                {{ $s->piece_count > 0 ? number_format((int) $s->piece_count, 0, ',', '.') : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-3" colspan="6">
                                {{ __('Tidak ada saldo untuk item ini pada penyaring yang dipilih.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>{{ __('Riwayat pergerakan') }}</strong>
            <span class="text-muted small ms-2">{{ __('Baris yang sudah tercatat tidak pernah diubah (P-01).') }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Waktu') }}</th>
                        <th scope="col">{{ __('Dari') }}</th>
                        <th scope="col">{{ __('Ke') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th scope="col">{{ __('Dokumen') }}</th>
                        <th scope="col">{{ __('Alasan') }}</th>
                        <th scope="col">{{ __('Oleh') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pergerakan as $m)
                        <tr @class(['table-warning' => $m->reverses_movement_id !== null])>
                            <td class="text-nowrap">{{ $m->occurred_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $m->fromBin?->code ?? __('Luar') }}</td>
                            <td>{{ $m->toBin?->code ?? __('Luar') }}</td>
                            <td class="text-end">{{ number_format((float) $m->qty_base, 2, ',', '.') }}</td>
                            <td>
                                {{ $m->document_number ?? '—' }}
                                @if ($m->reverses_movement_id !== null)
                                    <span class="badge text-bg-warning">{{ __('Pembalik') }}</span>
                                @endif
                            </td>
                            <td>{{ $m->reasonCode?->name ?? '—' }}</td>
                            <td>{{ $m->performer?->name ?? __('Sistem') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="7">
                                {{ __('Belum ada pergerakan untuk item ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pergerakan->hasPages())
            <div class="card-footer">{{ $pergerakan->links() }}</div>
        @endif
    </div>
</div>
