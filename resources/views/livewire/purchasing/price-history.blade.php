<div>
    @php($rp = fn ($n) => \App\Domain\Purchasing\Support\Money::format((float) $n))
    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <div class="fw-semibold">{{ $item->code }} — {{ $item->name }}</div>
                <div class="small text-muted">{{ __('Harga satuan per :u, seperti tercatat di PO (tanda PPN per baris).', ['u' => $item->baseUom?->code]) }}</div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="rh-vendor">{{ __('Vendor') }}</label>
                <select class="form-select" id="rh-vendor" wire:model.live="vendor">
                    <option value="">{{ __('Semua vendor') }}</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="rh-dari">{{ __('Dari') }}</label>
                <input class="form-control" id="rh-dari" type="date" wire:model.live="dari">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="rh-sampai">{{ __('Sampai') }}</label>
                <input class="form-control" id="rh-sampai" type="date" wire:model.live="sampai">
            </div>
            <div class="col-md-1 text-end">
                <a class="btn btn-outline-success" href="{{ $ekspor }}" title="{{ __('Ekspor Excel') }}"><i class="bi bi-file-earmark-excel"></i></a>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Ringkasan per vendor') }}</strong> <span class="small text-muted">{{ __('PO disetujui; rata-rata tertimbang jumlah') }}</span></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Vendor') }}</th>
                        <th class="text-end" scope="col">{{ __('Terakhir') }}</th>
                        @foreach (\App\Domain\Purchasing\Support\PurchasePriceHistory::JENDELA as $b)
                            <th class="text-end" scope="col">{{ __('Termurah :b bln', ['b' => $b]) }}</th>
                            <th class="text-end" scope="col">{{ __('Rata-rata :b bln', ['b' => $b]) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ringkas as $r)
                        <tr wire:key="rh-v-{{ $r['vendor_id'] }}">
                            <td>{{ $r['vendor'] }}</td>
                            <td class="text-end text-nowrap">{{ $rp($r['terakhir']['harga']) }} <div class="small text-muted">{{ $r['terakhir']['tanggal']->format('d/m/Y') }}{{ $r['terakhir']['ppn'] ? ' · '.__('termasuk PPN') : '' }}</div></td>
                            @foreach ($r['jendela'] as $j)
                                <td class="text-end text-nowrap">{{ $j['termurah'] === null ? '—' : $rp($j['termurah']) }}</td>
                                <td class="text-end text-nowrap">{{ $j['rata'] === null ? '—' : $rp($j['rata']) }} @if ($j['jumlah_po']) <div class="small text-muted">{{ $j['jumlah_po'] }} PO</div> @endif</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-3" colspan="8">{{ __('Belum ada PO disetujui untuk item ini dalam 12 bulan.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($grafik['garis'] !== [])
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Grafik harga satuan') }}</strong></div>
            <div class="card-body">
                <svg viewBox="0 0 600 215" class="w-100" style="max-height: 260px" role="img" aria-label="{{ __('Grafik harga satuan per vendor') }}" data-grafik-harga>
                    <line x1="40" y1="20" x2="40" y2="190" stroke="currentColor" stroke-opacity=".3"/>
                    <line x1="40" y1="190" x2="590" y2="190" stroke="currentColor" stroke-opacity=".3"/>
                    <text x="36" y="24" font-size="9" text-anchor="end" fill="currentColor">{{ number_format($grafik['max'], 0, ',', '.') }}</text>
                    <text x="36" y="192" font-size="9" text-anchor="end" fill="currentColor">{{ number_format($grafik['min'], 0, ',', '.') }}</text>
                    <text x="40" y="205" font-size="9" fill="currentColor">{{ $grafik['awal'] }}</text>
                    <text x="590" y="205" font-size="9" text-anchor="end" fill="currentColor">{{ $grafik['akhir'] }}</text>
                    @foreach ($grafik['garis'] as $g)
                        <polyline points="{{ $g['titik'] }}" fill="none" stroke="{{ $g['warna'] }}" stroke-width="2"/>
                        @foreach ($g['bulatan'] as $p)
                            <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3" fill="{{ $g['warna'] }}"><title>{{ $g['vendor'] }} · {{ $p['label'] }}</title></circle>
                        @endforeach
                    @endforeach
                </svg>
                <div class="small d-flex flex-wrap gap-3">
                    @foreach ($grafik['garis'] as $g)
                        <span><span class="d-inline-block rounded-circle me-1" style="width: .6rem; height: .6rem; background: {{ $g['warna'] }}"></span>{{ $g['vendor'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Baris PO') }}</strong></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Tanggal PO') }}</th>
                        <th scope="col">{{ __('Nomor PO') }}</th>
                        <th scope="col">{{ __('Vendor') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th class="text-end" scope="col">{{ __('Harga satuan') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $l)
                        <tr wire:key="rh-l-{{ $l['id'] }}" @class(['text-muted' => ! $l['dihitung']])>
                            <td class="small">{{ $l['tanggal']->format('d/m/Y') }}</td>
                            <td><a href="{{ route('purchase-orders.show', $l['po_id']) }}" wire:navigate>{{ $l['po'] }}</a></td>
                            <td>{{ $l['vendor'] }}</td>
                            <td class="text-end">{{ \App\Domain\Master\Support\QtyFormat::withUnit($l['jumlah'], $l['satuan']) }}</td>
                            <td class="text-end text-nowrap">{{ $rp($l['harga']) }} @if ($l['ppn']) <div class="small text-muted">{{ __('termasuk PPN') }}</div> @endif</td>
                            <td><span class="badge {{ $l['status']->badge() }}">{{ $l['status']->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-3" colspan="6">{{ __('Tidak ada baris PO pada penyaring ini.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
