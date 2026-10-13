<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $warehouse->name }}</h1>
            <p class="text-muted mb-0">
                {{ $warehouse->code }}
                <span class="badge text-bg-{{ $warehouse->is_active ? 'success' : 'secondary' }} ms-2">
                    {{ $warehouse->is_active ? __('Aktif') : __('Nonaktif') }}
                </span>
                @if ($warehouse->project)
                    <a class="badge text-bg-info ms-1 text-decoration-none" href="{{ route('projects.show', $warehouse->project_id) }}">{{ $warehouse->project->code }}</a>
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            {{-- A-254: denah 2D zona → rak → level → bin. --}}
            <a class="btn btn-primary" href="{{ route('warehouses.layout', $warehouse) }}"><i class="bi bi-grid-3x3-gap"></i> {{ __('Denah gudang') }}</a>
            <a class="btn btn-outline-secondary" href="{{ route('warehouses.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    {{-- Tautan cepat ke daftar yang sudah menyaring gudang ini (A-228). --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach ([
            ['stock.view', 'stock.index', ['warehouseFilter' => $warehouse->id], 'bi-boxes', __('Saldo stok')],
            ['reservation.view', 'stock.reservations', ['warehouseFilter' => $warehouse->id], 'bi-bookmark-check', __('Reservasi')],
            ['pick.view', 'picks.index', ['warehouseFilter' => $warehouse->id], 'bi-card-checklist', __('Tugas picking')],
            ['shipment.view', 'shipments.index', ['warehouseFilter' => $warehouse->id], 'bi-truck', __('Surat jalan')],
            ['receipt.view', 'receipts.index', ['warehouseFilter' => $warehouse->id], 'bi-box-arrow-in-down', __('Penerimaan')],
            ['putaway.view', 'putaways.index', ['warehouseFilter' => $warehouse->id], 'bi-inboxes', __('Put-away')],
            ['pr.view', 'purchase-requests.index', ['warehouse' => $warehouse->id], 'bi-cart3', __('Purchase Request')],
            ['po.view', 'purchase-orders.index', ['warehouse' => $warehouse->id], 'bi-receipt-cutoff', __('Purchase Order')],
        ] as [$izin, $rute, $param, $ikon, $label])
            @if (auth()->user()?->can($izin) && Route::has($rute))
                <a class="btn btn-sm btn-outline-secondary" href="{{ route($rute, $param) }}"><i class="bi {{ $ikon }}"></i> {{ $label }}</a>
            @endif
        @endforeach
    </div>

    <ul class="nav nav-tabs mb-3">
        @foreach (['lokasi' => __('Zona & rak'), 'bin' => __('Bin'), 'riwayat' => __('Riwayat')] as $kunci => $label)
            <li class="nav-item">
                <button class="nav-link {{ $tab === $kunci ? 'active' : '' }}" type="button"
                        wire:click="pilihTab('{{ $kunci }}')">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    @if ($tab === 'lokasi')
        {{-- A-410: Zona → Rak → Tingkat → Bin dengan popup, urutan, ubah, hapus/nonaktif, gabung/pisah. --}}
        @livewire('warehouse.warehouse-locations', ['warehouse' => $warehouse], key('zona-rak-'.$warehouse->id))
    @endif

    @if ($tab === 'bin')
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Kode bin') }}</th>
                            <th>{{ __('Jenis') }}</th>
                            <th>{{ __('Kategori') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bins as $bin)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $bin->code }}</span>
                                    @if ($bin->is_virtual)
                                        <span class="badge text-bg-info">{{ __('Virtual') }}</span>
                                    @endif
                                </td>
                                <td>{{ $bin->bin_type->label() }}</td>
                                <td>{{ $bin->storageCategory?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $bin->bin_status->badge() }}">
                                        {{ $bin->bin_status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-muted text-center py-4">{{ __('Belum ada bin.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($bins && $bins->hasPages())
                <div class="card-footer">{{ $bins->links() }}</div>
            @endif
        </div>
    @endif

    @if ($tab === 'riwayat')
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Waktu') }}</th>
                            <th>{{ __('Kejadian') }}</th>
                            <th>{{ __('Oleh') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riwayat as $baris)
                            <tr>
                                <td class="small">
                                    {{ $baris->created_at?->lokal()->format('d/m/Y H:i') }}
                                </td>
                                <td>{{ \App\Domain\Shared\Support\ActivityText::label($baris->description) }}</td>
                                <td>{{ $baris->causer?->name ?? __('Sistem') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-muted text-center py-4">{{ __('Belum ada riwayat.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($riwayat && $riwayat->hasPages())
                <div class="card-footer">{{ $riwayat->links() }}</div>
            @endif
        </div>
    @endif
</div>
