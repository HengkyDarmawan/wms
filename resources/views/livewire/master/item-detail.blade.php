<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $item->name }}</h1>
            <p class="text-muted mb-0">
                {{ $item->code }}
                <span class="badge text-bg-{{ $item->statusBadge() }} ms-2">{{ $item->status->label() }}</span>
            </p>
        </div>
        <div class="d-flex gap-2">
            @can('update', $item)
                <a class="btn btn-outline-secondary" href="{{ route('items.edit', $item) }}">{{ __('Ubah') }}</a>
            @endcan
            @can('label.print')
                <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
                   href="{{ route('labels.print', ['type' => 'label_item', 'ids' => $item->id]) }}">
                    <i class="bi bi-upc-scan"></i> {{ __('Cetak label') }}
                </a>
            @endcan
            <a class="btn btn-outline-secondary" href="{{ route('items.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    @if (session('pesan'))
        <div class="alert alert-success" role="alert">{{ session('pesan') }}</div>
    @endif

    <ul class="nav nav-tabs mb-3">
        @foreach ([
            'ringkasan' => __('Ringkasan'),
            'lot' => __('Lot'),
            'serial' => __('Serial'),
            'potongan' => __('Potongan'),
            'riwayat' => __('Riwayat'),
        ] as $kunci => $label)
            <li class="nav-item">
                <button class="nav-link {{ $tab === $kunci ? 'active' : '' }}" type="button"
                        wire:click="pilihTab('{{ $kunci }}')">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    @if ($tab === 'ringkasan')
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><strong>{{ __('Sifat item') }}</strong></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">{{ __('Kategori') }}</dt>
                            <dd class="col-sm-7">{{ $item->category?->path() ?? '—' }}</dd>

                            <dt class="col-sm-5">{{ __('Satuan dasar') }}</dt>
                            <dd class="col-sm-7">
                                {{ $item->baseUom?->code }} — {{ $item->baseUom?->name }}
                                <span class="text-muted">({{ $item->baseUom?->category?->name }})</span>
                            </dd>

                            <dt class="col-sm-5">{{ __('Mode pelacakan') }}</dt>
                            <dd class="col-sm-7">{{ $item->tracking_mode->label() }}</dd>

                            <dt class="col-sm-5">{{ __('Model kepemilikan') }}</dt>
                            <dd class="col-sm-7">{{ $item->ownership_model->label() }}</dd>

                            <dt class="col-sm-5">{{ __('Strategi pengambilan') }}</dt>
                            <dd class="col-sm-7">{{ $item->effectiveRemovalStrategy()->label() }}</dd>

                            <dt class="col-sm-5">{{ __('Kedaluwarsa') }}</dt>
                            <dd class="col-sm-7">{{ $item->has_expiry ? __('Ya') : __('Tidak') }}</dd>

                            <dt class="col-sm-5">{{ __('Wajib QC') }}</dt>
                            <dd class="col-sm-7">{{ $item->requires_qc ? __('Ya') : __('Tidak') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><strong>{{ __('Pemotongan & ambang stok') }}</strong></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-6">{{ __('Bisa dipotong') }}</dt>
                            <dd class="col-sm-6">{{ $item->is_cuttable ? __('Ya') : __('Tidak') }}</dd>

                            <dt class="col-sm-6">{{ __('Panjang minimum offcut') }}</dt>
                            <dd class="col-sm-6">
                                {{ $item->min_offcut_length ? (float) $item->min_offcut_length : '—' }}
                            </dd>

                            <dt class="col-sm-6">{{ __('Susut mata potong') }}</dt>
                            <dd class="col-sm-6">{{ $item->kerf ? (float) $item->kerf : '—' }}</dd>

                            <dt class="col-sm-6">{{ __('Titik pesan ulang') }}</dt>
                            <dd class="col-sm-6">{{ $item->reorder_point ? (float) $item->reorder_point : '—' }}</dd>

                            <dt class="col-sm-6">{{ __('Stok minimum') }}</dt>
                            <dd class="col-sm-6">{{ $item->min_stock ? (float) $item->min_stock : '—' }}</dd>

                            <dt class="col-sm-6">{{ __('Berat') }}</dt>
                            <dd class="col-sm-6">
                                {{ $item->weight ? (float) $item->weight.' '.($item->weightUom?->code ?? '') : '—' }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                @include('master.partials.item-photo')
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><strong>{{ __('Konversi satuan') }}</strong></div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Satuan') }}</th>
                                    <th class="text-end">{{ __('Setara satuan dasar') }}</th>
                                    <th>{{ __('Batang utuh') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($item->uomConversions as $konversi)
                                    <tr>
                                        <td>{{ $konversi->uom?->code }} — {{ $konversi->uom?->name }}</td>
                                        <td class="text-end">{{ (float) $konversi->qty_base }}</td>
                                        <td>{{ $konversi->is_nominal_piece ? __('Ya') : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-muted text-center py-3">
                                            {{ __('Belum ada konversi khusus.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><strong>{{ __('Vendor tetap') }}</strong></div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Vendor') }}</th>
                                    <th class="text-end">{{ __('Prioritas') }}</th>
                                    <th>{{ __('Catatan') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($item->itemVendors as $pemasok)
                                    <tr>
                                        <td>
                                            {{ $pemasok->vendor?->name }}
                                            @if ($pemasok->is_preferred)
                                                <span class="badge text-bg-success">{{ __('Utama') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $pemasok->priority }}</td>
                                        <td class="small text-muted">{{ $pemasok->notes ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-muted text-center py-3">
                                            {{ __('Belum ada vendor tetap.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($tab === 'lot')
        <div class="card">
            <div class="card-header text-muted small">
                {{ __('Lot lahir dari penerimaan barang, tidak bisa diketik di sini.') }}
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Nomor lot') }}</th>
                            <th>{{ __('Kedaluwarsa') }}</th>
                            <th>{{ __('Diterima') }}</th>
                            <th>{{ __('Vendor') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lots as $lot)
                            <tr>
                                <td class="fw-semibold">{{ $lot->lot_no }}</td>
                                <td>
                                    {{ $lot->expiry_date?->format('d/m/Y') ?? '—' }}
                                    @if ($lot->isExpired())
                                        <span class="badge text-bg-danger">{{ __('Kedaluwarsa') }}</span>
                                    @endif
                                </td>
                                <td>{{ $lot->received_at?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $lot->vendor?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-muted text-center py-4">{{ __('Belum ada lot.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($lots && $lots->hasPages())
                <div class="card-footer">{{ $lots->links() }}</div>
            @endif
        </div>
    @endif

    @if ($tab === 'serial')
        <div class="card">
            <div class="card-header text-muted small">
                {{ __('Serial lahir dari penerimaan barang. Masa pakai dipakai perawatan dan modul Akuntansi.') }}
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Serial') }}</th>
                            <th>{{ __('Keadaan') }}</th>
                            <th>{{ __('Proyek') }}</th>
                            <th>{{ __('Jatuh tempo kembali') }}</th>
                            <th class="text-end">{{ __('Sisa masa pakai') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($serials as $serial)
                            <tr>
                                <td class="fw-semibold">{{ $serial->serial_no }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $serial->stateBadge() }}">
                                        {{ $serial->asset_state->label() }}
                                    </span>
                                </td>
                                <td>{{ $serial->currentProject?->name ?? '—' }}</td>
                                <td>
                                    {{ $serial->due_return_date?->format('d/m/Y') ?? '—' }}
                                    @if ($serial->isOverdue())
                                        <span class="badge text-bg-danger">{{ __('Lewat tempo') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @php($sisa = $serial->remainingLifePercent())
                                    {{ $sisa === null ? '—' : $sisa.'%' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted text-center py-4">{{ __('Belum ada serial.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($serials && $serials->hasPages())
                <div class="card-footer">{{ $serials->links() }}</div>
            @endif
        </div>
    @endif

    @if ($tab === 'potongan')
        <div class="card">
            <div class="card-header text-muted small">
                {{ __('Potongan lahir dari penerimaan dan konversi. Sisa potongan menunjuk batang induknya.') }}
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Nomor potong') }}</th>
                            <th class="text-end">{{ __('Panjang') }}</th>
                            <th>{{ __('Jenis') }}</th>
                            <th>{{ __('Induk') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pieces as $piece)
                            <tr>
                                <td class="fw-semibold">{{ $piece->piece_no }}</td>
                                <td class="text-end">{{ (float) $piece->length }}</td>
                                <td>{{ $piece->is_offcut ? __('Sisa potongan') : __('Batang utuh') }}</td>
                                <td>{{ $piece->parent_piece_id ?? '—' }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $piece->is_consumed ? 'secondary' : 'success' }}">
                                        {{ $piece->is_consumed ? __('Terpakai') : __('Tersedia') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted text-center py-4">{{ __('Belum ada potongan.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($pieces && $pieces->hasPages())
                <div class="card-footer">{{ $pieces->links() }}</div>
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
                                <td class="small">{{ $baris->created_at?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d/m/Y H:i') }}</td>
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
