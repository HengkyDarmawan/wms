<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $receiptId ? __('Ubah penerimaan (draf)') : __('Penerimaan baru') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Draf belum menyentuh stok. Stok tercatat saat GRN diterima.') }}
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('receipts.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '')
                <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span>
            @endif
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Sumber & gudang') }}</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="grn-sumber">{{ __('Sumber') }} <span class="wajib">*</span></label>
                <select class="form-select" id="grn-sumber" wire:model.live="form.receipt_type" @disabled($receiptId)>
                    @foreach ($types as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($form['receipt_type'] === 'return')
                <div class="col-md-8">
                    <label class="form-label" for="grn-ret">{{ __('Retur dari proyek (RET)') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.goods_return_id') is-invalid @enderror" id="grn-ret"
                            wire:model.live="form.goods_return_id" @disabled($receiptId)>
                        <option value="">{{ __('Pilih retur…') }}</option>
                        @foreach ($returnDocs as $r)
                            <option value="{{ $r->id }}">{{ $r->number }} ({{ $r->project?->code }})</option>
                        @endforeach
                        @if ($ret && ! $returnDocs->contains('id', $ret->id))
                            <option value="{{ $ret->id }}">{{ $ret->number }}</option>
                        @endif
                    </select>
                    @error('form.goods_return_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @elseif ($form['receipt_type'] === 'transfer')
                <div class="col-md-8">
                    <label class="form-label" for="grn-sj">{{ __('Surat jalan transfer') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.shipment_id') is-invalid @enderror" id="grn-sj"
                            wire:model.live="form.shipment_id" @disabled($receiptId)>
                        <option value="">{{ __('Pilih surat jalan…') }}</option>
                        @foreach ($incoming as $s)
                            <option value="{{ $s->id }}">{{ $s->number }} ({{ $s->warehouse?->code }})</option>
                        @endforeach
                    </select>
                    @error('form.shipment_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @else
                <div class="col-md-4">
                    <label class="form-label" for="grn-gudang">{{ __('Gudang penerima') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.warehouse_id') is-invalid @enderror" id="grn-gudang"
                            wire:model="form.warehouse_id" @disabled($receiptId)>
                        <option value="">{{ __('Pilih gudang…') }}</option>
                        @foreach ($warehouses as $g)
                            <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</option>
                        @endforeach
                    </select>
                    @error('form.warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="grn-vendor">{{ __('Vendor') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.vendor_id') is-invalid @enderror" id="grn-vendor"
                            wire:model.live="form.vendor_id">
                        <option value="">{{ __('Pilih vendor…') }}</option>
                        @foreach ($vendors as $v)
                            <option value="{{ $v->id }}">{{ $v->code }} — {{ $v->name }}</option>
                        @endforeach
                    </select>
                    @error('form.vendor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="grn-sjv">{{ __('No. surat jalan vendor') }}</label>
                    <input class="form-control" id="grn-sjv" type="text" wire:model="form.vendor_doc_no">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="grn-po">{{ __('Referensi PO') }}</label>
                    <input class="form-control" id="grn-po" type="text" wire:model="form.po_ref"
                           placeholder="{{ __('Opsional') }}">
                </div>
                @if ($returns->isNotEmpty())
                    <div class="col-md-4">
                        <label class="form-label" for="grn-rtv">{{ __('Pengganti untuk RTV') }}</label>
                        <select class="form-select" id="grn-rtv" wire:model="form.vendor_return_id">
                            <option value="">{{ __('Bukan barang pengganti') }}</option>
                            @foreach ($returns as $r)
                                <option value="{{ $r->id }}">{{ $r->number }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            @endif

            <div class="col-12">
                <label class="form-label" for="grn-catatan">{{ __('Keterangan') }}</label>
                <input class="form-control" id="grn-catatan" type="text" wire:model="form.notes"
                       placeholder="{{ __('Opsional') }}">
            </div>
        </div>
    </div>

    @if ($form['receipt_type'] === 'return')
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Baris retur') }}</strong> <span class="text-muted small">— {{ __('masuk bin Retur, dipilah dari detail RET') }}</span></div>
            @if ($ret === null)
                <div class="card-body text-muted">{{ __('Pilih RET yang sedang diproses.') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Item') }}</th>
                                <th class="text-end" scope="col">{{ __('Dikirim') }}</th>
                                <th scope="col">{{ __('Diterima di gudang') }} <span class="wajib">*</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($retLines as $b)
                                <tr wire:key="retl-{{ $b['line']->id }}">
                                    <td>{{ $b['line']->item?->code }} <div class="small text-muted">{{ $b['line']->item?->name }} {{ $b['line']->trackingLabel() }}</div></td>
                                    <td class="text-end">{{ number_format($b['max'], 2, ',', '.') }}</td>
                                    <td>
                                        @if ($b['max'] > 0)
                                            <input class="form-control form-control-sm" type="number" step="0.0001" min="0"
                                                   wire:model="returnQty.{{ $b['line']->id }}">
                                        @else
                                            <span class="text-muted small">{{ __('Menunggu selisih pengiriman') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('form.qty_received') <div class="text-danger small p-3">{{ $message }}</div> @enderror
            @endif
        </div>
    @elseif ($form['receipt_type'] === 'transfer')
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Baris surat jalan') }}</strong></div>
            @if ($sj === null)
                <div class="card-body text-muted">{{ __('Pilih surat jalan transfer yang sudah punya bukti terima.') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Item') }}</th>
                                <th class="text-end" scope="col">{{ __('Dikirim') }}</th>
                                <th class="text-end" scope="col">{{ __('Diterima baik') }}</th>
                                <th scope="col">{{ __('Diterima di gudang') }} <span class="wajib">*</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sj->lines as $l)
                                <tr wire:key="sjl-{{ $l->id }}">
                                    <td>{{ $l->pickTaskLine?->item?->code }} <div class="small text-muted">{{ $l->pickTaskLine?->item?->name }}</div></td>
                                    <td class="text-end">{{ number_format((float) $l->qty_shipped, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format((float) $l->qty_delivered, 2, ',', '.') }}</td>
                                    <td>
                                        @if ((float) $l->qty_delivered > 0)
                                            <input class="form-control form-control-sm" type="number" step="0.0001" min="0"
                                                   wire:model="transferQty.{{ $l->id }}">
                                        @else
                                            <span class="text-muted small">{{ __('Menunggu selisih pengiriman') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('form.qty_received') <div class="text-danger small p-3">{{ $message }}</div> @enderror
            @endif
        </div>
    @else
        @if ($openOrders->isNotEmpty())
            <div class="card mb-3 border-info">
                <div class="card-header"><strong>{{ __('Pesanan PRQ ke vendor ini') }}</strong> <span class="small text-muted">{{ __('pilih untuk menambah baris yang merujuk catatan pemesanan') }}</span></div>
                <ul class="list-group list-group-flush small">
                    @foreach ($openOrders as $ol)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2" wire:key="grn-po-{{ $ol->id }}">
                            <span>
                                <strong>{{ $ol->order?->purchaseRequest?->number }}</strong> · {{ $ol->order?->reference() }}
                                · {{ $ol->line?->item?->code }} — {{ $ol->line?->item?->name }}
                                · {{ __('sisa') }} {{ number_format($ol->outstandingQty(), 2, ',', '.') }}
                            </span>
                            <button class="btn btn-sm btn-outline-info" type="button" wire:click="pakaiPesanan({{ $ol->id }})">{{ __('Tambah') }}</button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ __('Baris barang') }}</strong>
                <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahBaris">{{ __('Tambah baris') }}</button>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    {{ __('Item berserial: tulis satu nomor serial per baris di kolom Serial/Potongan. Item per potong: tulis panjang tiap potongan.') }}
                </p>
                @foreach ($rows as $i => $r)
                    @php($mode = $modes[(int) ($r['item_id'] ?: 0)] ?? null)
                    <div class="row g-2 align-items-end border-bottom pb-2 mb-2" wire:key="grn-row-{{ $i }}">
                        <div class="col-md-3">
                            <label class="form-label small" for="row-item-{{ $i }}">{{ __('Item') }} <span class="wajib">*</span>
                                @if (($r['order_line_id'] ?? '') !== '') <span class="badge text-bg-info">{{ __('PRQ') }}</span> @endif
                            </label>
                            <select class="form-select form-select-sm" id="row-item-{{ $i }}" wire:model.live="rows.{{ $i }}.item_id" @disabled(($r['order_line_id'] ?? '') !== '')>
                                <option value="">{{ __('Pilih item…') }}</option>
                                @foreach ($items as $it)
                                    <option value="{{ $it->id }}">{{ $it->code }} — {{ $it->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($mode === null || in_array($mode->value, ['none', 'lot'], true))
                            <div class="col-md-2">
                                <label class="form-label small" for="row-qty-{{ $i }}">{{ __('Jumlah') }} <span class="wajib">*</span></label>
                                <input class="form-control form-control-sm" id="row-qty-{{ $i }}" type="number" step="0.0001" min="0"
                                       wire:model="rows.{{ $i }}.qty">
                            </div>
                        @endif
                        @if ($mode?->value === 'lot')
                            <div class="col-md-2">
                                <label class="form-label small" for="row-lot-{{ $i }}">{{ __('No. lot') }} <span class="wajib">*</span></label>
                                <input class="form-control form-control-sm" id="row-lot-{{ $i }}" type="text" data-scan wire:model="rows.{{ $i }}.lot_no">
                            </div>
                        @endif
                        @if (in_array($mode?->value, ['lot', 'serial'], true))
                            <div class="col-md-2">
                                <label class="form-label small" for="row-exp-{{ $i }}">{{ __('Kedaluwarsa') }}</label>
                                <input class="form-control form-control-sm" id="row-exp-{{ $i }}" type="date" wire:model="rows.{{ $i }}.expiry_date">
                            </div>
                        @endif
                        @if (in_array($mode?->value, ['serial', 'piece'], true))
                            <div class="col-md-3">
                                <label class="form-label small" for="row-unit-{{ $i }}">
                                    {{ $mode->value === 'serial' ? __('Nomor serial') : __('Panjang potongan') }} <span class="wajib">*</span>
                                </label>
                                <textarea class="form-control form-control-sm" id="row-unit-{{ $i }}" rows="2"
                                          wire:model="rows.{{ $i }}.units"></textarea>
                            </div>
                        @endif
                        <div class="col-md-1">
                            <button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusBaris({{ $i }})"
                                    aria-label="{{ __('Hapus baris') }}"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                @endforeach
                @foreach (['item_id', 'lot_no', 'serial_no', 'piece_length', 'expiry_date', 'qty_received'] as $f)
                    @error('form.'.$f) <div class="text-danger small">{{ $message }}</div> @enderror
                @endforeach
            </div>
        </div>
    @endif

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan draf') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('receipts.index') }}">{{ __('Batal') }}</a>
    </div>
</div>
