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
        <a class="btn btn-outline-secondary" href="{{ route('warehouses.index') }}">{{ __('Kembali') }}</a>
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
        @can('create', \App\Domain\Warehouse\Models\Bin::class)
            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('Tambah zona') }}</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="zona-kode">{{ __('Kode zona') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.zone_code') is-invalid @enderror" id="zona-kode"
                               type="text" wire:model="form.zone_code" placeholder="A">
                        @error('form.zone_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="zona-nama">{{ __('Nama zona') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.zone_name') is-invalid @enderror" id="zona-nama"
                               type="text" wire:model="form.zone_name" placeholder="{{ __('Material besi') }}">
                        @error('form.zone_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="button" wire:click="tambahZona">
                            {{ __('Tambah zona') }}
                        </button>
                    </div>
                </div>
            </div>
        @endcan

        @forelse ($zones as $zona)
            <div class="card mb-3" wire:key="zona-{{ $zona->id }}">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <strong>{{ $zona->code }}</strong>
                        <span class="text-muted ms-2">{{ $zona->name }}</span>
                    </div>
                    @can('create', \App\Domain\Warehouse\Models\Bin::class)
                        <div class="d-flex gap-2">
                            <input class="form-control form-control-sm" style="max-width: 8rem"
                                   type="text" wire:model="form.rack_code" placeholder="{{ __('Kode rak') }}"
                                   aria-label="{{ __('Kode rak') }}">
                            <button class="btn btn-sm btn-outline-primary" type="button"
                                    wire:click="tambahRak({{ $zona->id }})">{{ __('Tambah rak') }}</button>
                        </div>
                    @endcan
                </div>

                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Rak') }}</th>
                                <th>{{ __('Level') }}</th>
                                <th class="text-end">{{ __('Bin') }}</th>
                                <th class="text-end">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($zona->racks as $rak)
                                @forelse ($rak->levels as $level)
                                    <tr wire:key="level-{{ $level->id }}">
                                        <td class="fw-semibold">{{ $loop->first ? $rak->code : '' }}</td>
                                        <td>{{ $level->code }}</td>
                                        <td class="text-end">{{ $level->bins_count }}</td>
                                        <td class="text-end">
                                            @can('create', \App\Domain\Warehouse\Models\Bin::class)
                                                <button class="btn btn-sm btn-outline-primary" type="button"
                                                        wire:click="mintaBuatBin({{ $level->id }})">
                                                    {{ __('Buat bin') }}
                                                </button>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr wire:key="rak-{{ $rak->id }}">
                                        <td class="fw-semibold">{{ $rak->code }}</td>
                                        <td colspan="2" class="text-muted">{{ __('Belum ada level.') }}</td>
                                        <td class="text-end">
                                            @can('create', \App\Domain\Warehouse\Models\Bin::class)
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <input class="form-control form-control-sm" style="max-width: 6rem"
                                                           type="text" wire:model="form.level_code"
                                                           placeholder="L1" aria-label="{{ __('Kode level') }}">
                                                    <button class="btn btn-sm btn-outline-primary" type="button"
                                                            wire:click="tambahLevel({{ $rak->id }})">
                                                        {{ __('Tambah level') }}
                                                    </button>
                                                </div>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforelse
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted text-center py-3">{{ __('Belum ada rak di zona ini.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($zona->racks->isNotEmpty())
                    @can('create', \App\Domain\Warehouse\Models\Bin::class)
                        <div class="card-footer d-flex flex-wrap gap-2 align-items-end">
                            <div>
                                <label class="form-label small" for="level-baru-{{ $zona->id }}">{{ __('Kode level baru') }}</label>
                                <input class="form-control form-control-sm" style="max-width: 6rem"
                                       id="level-baru-{{ $zona->id }}" type="text" wire:model="form.level_code"
                                       placeholder="L2">
                            </div>
                            @foreach ($zona->racks as $rak)
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        wire:click="tambahLevel({{ $rak->id }})">
                                    {{ __('Tambah ke rak') }} {{ $rak->code }}
                                </button>
                            @endforeach
                        </div>
                    @endcan
                @endif
            </div>
        @empty
            <div class="card">
                <div class="card-body text-muted">
                    {{ __('Belum ada zona. Gudang site sederhana cukup satu zona dan satu bin.') }}
                </div>
            </div>
        @endforelse

        @if ($generatingLevelId)
            <div class="card border-primary mb-3">
                <div class="card-header"><strong>{{ __('Buat bin massal') }}</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-2">
                        <label class="form-label" for="gen-jumlah">{{ __('Jumlah bin') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('generator.jumlah') is-invalid @enderror" id="gen-jumlah"
                               type="number" min="1" max="200" wire:model="generator.jumlah">
                        @error('generator.jumlah') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="gen-prefix">{{ __('Awalan kode') }}</label>
                        <input class="form-control" id="gen-prefix" type="text" wire:model="generator.prefix"
                               placeholder="B">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="gen-kategori">{{ __('Kategori penyimpanan') }}</label>
                        <select class="form-select" id="gen-kategori" wire:model="generator.storage_category_id">
                            <option value="">{{ __('Tidak ditentukan') }}</option>
                            @foreach ($storageCategories as $kategori)
                                <option value="{{ $kategori->id }}">{{ $kategori->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="gen-kapasitas">{{ __('Kapasitas jumlah per bin') }}</label>
                        <input class="form-control @error('generator.capacity_qty') is-invalid @enderror"
                               id="gen-kapasitas" type="text" wire:model="generator.capacity_qty">
                        @error('generator.capacity_qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-primary" type="button" wire:click="buatBinMassal">{{ __('Buat') }}</button>
                    <button class="btn btn-outline-secondary" type="button" wire:click="batalBuatBin">{{ __('Batal') }}</button>
                </div>
            </div>
        @endif
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
                                <td>{{ $baris->description }}</td>
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
