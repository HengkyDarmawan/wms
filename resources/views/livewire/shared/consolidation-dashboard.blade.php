<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Ringkasan opname & waste') }}</h1>
            <p class="text-muted mb-0">{{ __('Akurasi stok dan waste lintas gudang. Angka akurasi sama dengan laporan Tren akurasi stok.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <select class="form-select" wire:model.live="bulan" aria-label="{{ __('Periode') }}" style="width: auto">
                <option value="3">{{ __('3 bulan terakhir') }}</option>
                <option value="6">{{ __('6 bulan terakhir') }}</option>
                <option value="12">{{ __('12 bulan terakhir') }}</option>
            </select>
            <select class="form-select" wire:model.live="gudang" aria-label="{{ __('Gudang') }}" style="width: auto">
                <option value="">{{ __('Semua gudang') }}</option>
                @foreach ($gudangOpsi as $g)
                    <option value="{{ $g->id }}">{{ $g->code }} · {{ $g->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @php($warna = fn (?float $a) => $a === null ? '' : ($a >= \App\Domain\Shared\Livewire\ConsolidationDashboard::AKURASI_BAIK ? 'text-success' : ($a >= \App\Domain\Shared\Livewire\ConsolidationDashboard::AKURASI_WASPADA ? 'text-warning-emphasis' : 'text-danger')))

    @if ($akurasi !== null)
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ __('Akurasi stok per gudang (%)') }}</strong>
                <span class="small text-muted">{{ __('baris hitung tanpa selisih ÷ baris dihitung; hijau ≥ :b %, kuning ≥ :w %', ['b' => 98, 'w' => 95]) }}</span>
            </div>
            @if ($akurasi->isEmpty())
                <div class="card-body text-muted small">{{ __('Belum ada sesi opname yang direkonsiliasi pada periode ini.') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Gudang') }}</th>
                                @foreach ($bulanList as $b) <th class="text-end">{{ $b }}</th> @endforeach
                                <th class="text-end">{{ __('Periode') }}</th>
                                <th class="text-end">{{ __('Sesi') }}</th>
                                <th class="text-end">{{ __('Selisih besar') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($akurasi as $kode => $r)
                                <tr>
                                    <td class="fw-semibold">{{ $kode }}</td>
                                    @foreach ($bulanList as $b)
                                        @php($a = $r['bulan'][$b] ?? null)
                                        <td class="text-end {{ $warna($a) }}">{{ $a === null ? '-' : number_format($a, 1, ',', '.') }}</td>
                                    @endforeach
                                    <td class="text-end fw-semibold {{ $warna($r['akurasi']) }}">{{ $r['akurasi'] === null ? '-' : number_format($r['akurasi'], 1, ',', '.') }}</td>
                                    <td class="text-end">{{ $r['sesi'] }}</td>
                                    <td class="text-end {{ $r['besar'] > 0 ? 'text-danger' : '' }}">{{ $r['besar'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ __('Akar masalah selisih teratas') }}</strong>
                <a wire:navigate class="small" href="{{ route('reports.show', 'akar-masalah') }}">{{ __('Laporan lengkap') }}</a>
            </div>
            @if ($akar->isEmpty())
                <div class="card-body text-muted small">{{ __('Belum ada akar masalah tercatat pada periode ini.') }}</div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($akar as $a)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $a['akar'] }}</span><span class="text-muted small">{{ __(':n baris · :s sesi', ['n' => $a['baris'], 's' => $a['sesi']]) }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($waste !== null)
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header"><strong>{{ __('Berita acara waste per gudang') }}</strong> <span class="small text-muted">{{ __('ditutup dalam periode') }}</span></div>
                    @if ($waste['perGudang']->isEmpty())
                        <div class="card-body text-muted small">{{ __('Belum ada waste pada periode ini.') }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>{{ __('Gudang') }}</th><th class="text-end">{{ __('BA') }}</th><th class="text-end">{{ __('Dibuang') }}</th><th class="text-end">{{ __('Dijual scrap') }}</th><th class="text-end">{{ __('Dipakai ulang') }}</th><th class="text-end">{{ __('Belum di-BA') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($waste['perGudang'] as $w)
                                        <tr>
                                            <td class="fw-semibold">{{ $w->gudang }}</td>
                                            <td class="text-end">{{ $w->ba }}</td>
                                            <td class="text-end">{{ $w->dibuang }}</td>
                                            <td class="text-end">{{ $w->dijual }}</td>
                                            <td class="text-end">{{ $w->dipakai_ulang }}</td>
                                            <td class="text-end {{ $w->menunggu > 0 ? 'text-warning-emphasis fw-semibold' : '' }}">{{ $w->menunggu > 0 ? __(':n item', ['n' => $w->menunggu]) : '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><strong>{{ __('Item waste terbanyak') }}</strong> <span class="small text-muted">{{ __('satuan dasar') }}</span></div>
                    @if ($waste['item']->isEmpty())
                        <div class="card-body text-muted small">{{ __('Belum ada waste yang ditutup pada periode ini.') }}</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($waste['item'] as $i)
                                <li class="list-group-item d-flex justify-content-between gap-2"><span>{{ $i->code }} · {{ $i->name }}</span><span class="text-nowrap">{{ \App\Domain\Master\Support\QtyFormat::number((float) $i->qty) }} {{ $i->uom }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
