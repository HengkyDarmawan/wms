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
                    <x-pilih model="form.goods_return_id" id="grn-ret" live wajib :label="__('Retur dari proyek (RET)')"
                             :disabled="(bool) $receiptId" :kosong="__('Pilih retur…')"
                             :options="$returnDocs->map(fn ($r) => ['value' => $r->id, 'text' => $r->number, 'sub' => $r->project?->code])
                                 ->when($ret && ! $returnDocs->contains('id', $ret->id), fn ($c) => $c->push(['value' => $ret->id, 'text' => $ret->number]))->all()" />
                </div>
            @elseif ($form['receipt_type'] === 'transfer')
                <div class="col-md-8">
                    <x-pilih model="form.shipment_id" id="grn-sj" live wajib :label="__('Surat jalan transfer')"
                             :disabled="(bool) $receiptId" :kosong="__('Pilih surat jalan…')"
                             :options="$incoming->map(fn ($s) => ['value' => $s->id, 'text' => $s->number, 'sub' => $s->warehouse?->code])->all()" />
                </div>
            @else
                <div class="col-md-4">
                    <x-pilih model="form.warehouse_id" id="grn-gudang" wajib :label="__('Gudang penerima')"
                             :disabled="(bool) $receiptId" :kosong="__('Pilih gudang…')"
                             :options="$warehouses->map(fn ($g) => ['value' => $g->id, 'text' => $g->code.' — '.$g->name])->all()" />
                </div>
                <div class="col-md-4">
                    {{-- A-391: vendor dicari ke server (kode atau nama). --}}
                    <x-pilih model="form.vendor_id" id="grn-vendor" server live wajib :label="__('Vendor')"
                             :kosong="__('Pilih vendor…')" :options="$opsiVendor" />
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
                        <x-pilih model="form.vendor_return_id" id="grn-rtv" :label="__('Pengganti untuk RTV')"
                                 :kosong="__('Bukan barang pengganti')"
                                 :options="$returns->map(fn ($r) => ['value' => $r->id, 'text' => $r->number])->all()" />
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
                <p class="small text-muted px-3 mb-2">{{ __('Jumlah di atas yang dikirim dicatat sebagai kelebihan dan diajukan lewat penyesuaian stok (ADJ), bukan lewat GRN.') }}</p>
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
                <p class="small text-muted px-3 mb-2">{{ __('Jumlah di atas yang dikirim dicatat sebagai kelebihan dan diajukan lewat penyesuaian stok (ADJ), bukan lewat GRN.') }}</p>
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
                    {{ __('Catat per baris: jumlah menurut surat jalan vendor, yang Baik, yang Rusak (dengan alasan), dan yang Kurang. Baik terisi otomatis; Kurang dihitung otomatis bila dikosongkan. Barang rusak masuk bin Karantina dan bisa langsung diretur ke vendor.') }}
                    {{-- Bantuan hanya untuk jenis barang yang saklarnya menyala (A-284). --}}
                    @if (\App\Domain\Master\Support\StockFeatures::on('serial')) {{ __('Item berserial: tulis satu nomor serial per baris.') }} @endif
                    @if (\App\Domain\Master\Support\StockFeatures::piece()) {{ __('Item per potong: tulis panjang tiap potongan.') }} @endif
                </p>
                @foreach ($rows as $i => $r)
                    @php($mode = $modes[(int) ($r['item_id'] ?: 0)] ?? null)
                    @php($opsi = $unitOpsi[(int) ($r['item_id'] ?: 0)] ?? null)
                    @php($perUnit = in_array($mode?->value, ['serial', 'piece'], true))
                    {{-- A-291: satuan terpilih tampil sebagai akhiran kotak jumlah. --}}
                    @php($kodeSatuan = \App\Domain\Master\Support\UnitInput::selectedCode($r, $opsi, $satuanLain))
                    <div class="row g-2 align-items-end border-bottom pb-2 mb-2" wire:key="grn-row-{{ $i }}">
                        <div class="col-md-3">
                            <label class="form-label small" for="row-item-{{ $i }}">{{ __('Item') }} <span class="wajib">*</span>
                                @if (($r['order_line_id'] ?? '') !== '') <span class="badge text-bg-info">{{ __('PRQ') }}</span> @endif
                                @if ($r['bonus'] ?? false) <span class="badge text-bg-success">{{ __('Bonus') }}</span> @endif
                            </label>
                            {{-- A-391: item dicari ke server; `kunci` = jumlah baris supaya kotak dibuat ulang saat baris dihapus. --}}
                            <x-pilih model="rows.{{ $i }}.item_id" id="row-item-{{ $i }}" server live kecil :kunci="(string) count($rows)"
                                     :disabled="($r['order_line_id'] ?? '') !== ''" :kosong="__('Pilih item…')" :options="$opsiItem[$i] ?? []" />
                        </div>
                        @if (! $perUnit)
                            <div class="col-md-2">
                                @include('livewire.master.partials.unit-picker', ['prefix' => 'rows.'.$i, 'row' => $r, 'opsi' => $opsi, 'satuanLain' => $satuanLain, 'hasil' => $hasilSatuan[$i] ?? null, 'idAwal' => 'row-'.$i])
                            </div>
                        @endif
                        @if ($mode?->value !== 'piece')
                            <div class="col-6 col-md-3 col-xl-2">
                                <label class="form-label small" for="row-vendor-{{ $i }}" title="{{ __('Jumlah menurut surat jalan vendor') }}">{{ __('Dikirim vendor') }}</label>
                                <div class="input-group input-group-sm">
                                    <input class="form-control" id="row-vendor-{{ $i }}" type="number" step="0.0001" min="0"
                                           wire:model.live.debounce.500ms="rows.{{ $i }}.vendor">
                                    @if ($kodeSatuan) <span class="input-group-text" data-akhiran-satuan>{{ $kodeSatuan }}</span> @endif
                                </div>
                            </div>
                        @endif
                        @if (! $perUnit)
                            <div class="col-6 col-md-3 col-xl-2">
                                <label class="form-label small" for="row-qty-{{ $i }}">{{ __('Baik') }} <span class="wajib">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input class="form-control" id="row-qty-{{ $i }}" type="number" step="0.0001" min="0"
                                           wire:model.live.debounce.500ms="rows.{{ $i }}.qty">
                                    @if ($kodeSatuan) <span class="input-group-text" data-akhiran-satuan>{{ $kodeSatuan }}</span> @endif
                                </div>
                            </div>
                            <div class="col-6 col-md-3 col-xl-2">
                                <label class="form-label small" for="row-rusak-{{ $i }}">{{ __('Rusak') }}</label>
                                <div class="input-group input-group-sm">
                                    <input class="form-control" id="row-rusak-{{ $i }}" type="number" step="0.0001" min="0"
                                           wire:model.live.debounce.500ms="rows.{{ $i }}.damaged">
                                    @if ($kodeSatuan) <span class="input-group-text" data-akhiran-satuan>{{ $kodeSatuan }}</span> @endif
                                </div>
                                @if ($rusakDasar = \App\Domain\Master\Support\UnitInput::baseText($r, $opsi, $satuanLain, $r['damaged'] ?? null))
                                    <div class="form-text" data-rusak-dasar>{{ $rusakDasar }}</div>
                                @endif
                            </div>
                        @endif
                        @if ($mode?->value !== 'piece')
                            <div class="col-6 col-md-3 col-xl-2">
                                @php($otomatis = is_numeric($r['vendor'] ?? null) ? max(0, (float) $r['vendor'] - (float) ($r['qty'] ?: 0) - (float) ($r['damaged'] ?: 0)) : null)
                                <label class="form-label small" for="row-kurang-{{ $i }}">{{ __('Kurang') }}</label>
                                <div class="input-group input-group-sm">
                                    <input class="form-control" id="row-kurang-{{ $i }}" type="number" step="0.0001" min="0"
                                           wire:model.live.debounce.500ms="rows.{{ $i }}.short"
                                           placeholder="{{ $otomatis === null ? '' : __('otomatis').' '.\App\Domain\Master\Support\QtyFormat::number($otomatis) }}">
                                    @if ($kodeSatuan) <span class="input-group-text" data-akhiran-satuan>{{ $kodeSatuan }}</span> @endif
                                </div>
                            </div>
                        @endif
                        @if ($mode?->value === 'lot')
                            <div class="col-md-2">
                                <label class="form-label small" for="row-lot-{{ $i }}">{{ __('Batch vendor') }} <span class="text-muted" title="{{ __('Nomor lot dibuat otomatis dari nomor GRN saat diterima') }}">({{ __('opsional') }})</span></label>
                                <input class="form-control form-control-sm" id="row-lot-{{ $i }}" type="text" data-scan wire:model="rows.{{ $i }}.lot_no">
                            </div>
                        @endif
                        @if (in_array($mode?->value, ['lot', 'serial'], true))
                            <div class="col-md-2">
                                <label class="form-label small" for="row-exp-{{ $i }}">{{ __('Kedaluwarsa') }}</label>
                                <input class="form-control form-control-sm" id="row-exp-{{ $i }}" type="date" wire:model="rows.{{ $i }}.expiry_date">
                            </div>
                        @endif
                        @if ($perUnit)
                            <div class="col-md-3">
                                <label class="form-label small" for="row-unit-{{ $i }}">
                                    {{ $mode->value === 'serial' ? __('Nomor serial (baik)') : __('Panjang potongan (baik)') }}
                                </label>
                                <textarea class="form-control form-control-sm" id="row-unit-{{ $i }}" rows="2"
                                          wire:model="rows.{{ $i }}.units"></textarea>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" id="row-ada-rusak-{{ $i }}" type="checkbox" wire:model.live="rows.{{ $i }}.ada_rusak">
                                    <label class="form-check-label small" for="row-ada-rusak-{{ $i }}">{{ __('Ada yang rusak') }}</label>
                                </div>
                            </div>
                            @if ($r['ada_rusak'] ?? false)
                                <div class="col-md-2">
                                    <label class="form-label small" for="row-unit-rusak-{{ $i }}">
                                        {{ $mode->value === 'serial' ? __('Nomor serial rusak') : __('Panjang potongan rusak') }}
                                    </label>
                                    <textarea class="form-control form-control-sm" id="row-unit-rusak-{{ $i }}" rows="2"
                                              wire:model="rows.{{ $i }}.units_damaged"></textarea>
                                </div>
                            @endif
                        @endif
                        @if ((! $perUnit && is_numeric($r['damaged'] ?? null) && (float) $r['damaged'] > 0) || ($perUnit && ($r['ada_rusak'] ?? false)))
                            <div class="col-md-2">
                                <label class="form-label small" for="row-alasan-rusak-{{ $i }}">{{ __('Alasan rusak') }} <span class="wajib">*</span></label>
                                <select class="form-select form-select-sm" id="row-alasan-rusak-{{ $i }}" wire:model="rows.{{ $i }}.damage_reason">
                                    <option value="">{{ __('Pilih alasan…') }}</option>
                                    @foreach ($alasanRusak as $kode => $label)
                                        <option value="{{ $kode }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @if (($form['receipt_type'] ?? '') === 'vendor')
                            <div class="col-md-2">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" id="row-bonus-{{ $i }}" type="checkbox" wire:model.live="rows.{{ $i }}.bonus">
                                    <label class="form-check-label small" for="row-bonus-{{ $i }}">{{ __('Bonus vendor') }}</label>
                                </div>
                                @php($sisa = ($r['order_line_id'] ?? '') !== '' ? ($sisaPesanan[(int) $r['order_line_id']] ?? null) : null)
                                @if ($sisa !== null && ($r['uom'] ?? '') === '' && is_numeric($r['qty'] ?? null) && (float) $r['qty'] + (float) ($r['damaged'] ?: 0) - $sisa > 0.00005)
                                    <button class="btn btn-sm btn-outline-success" type="button" wire:click="pisahkanBonus({{ $i }})">{{ __('Pisahkan kelebihan jadi bonus') }}</button>
                                    <div class="form-text">{{ __('Sisa pesanan') }} {{ rtrim(rtrim(number_format($sisa, 4, ',', '.'), '0'), ',') }}</div>
                                @endif
                            </div>
                            @if ($r['bonus'] ?? false)
                                <div class="col-md-3">
                                    <label class="form-label small" for="row-notes-{{ $i }}">{{ __('Keterangan bonus') }} <span class="wajib">*</span></label>
                                    <input class="form-control form-control-sm" id="row-notes-{{ $i }}" type="text" maxlength="255" wire:model="rows.{{ $i }}.notes" placeholder="{{ __('mis. promo beli 2 gratis 1') }}">
                                </div>
                            @endif
                        @endif
                        <div class="col-md-1">
                            <button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusBaris({{ $i }})"
                                    aria-label="{{ __('Hapus baris') }}"><i class="bi bi-x-lg"></i></button>
                        </div>
                        @if (! $perUnit && ($r['uom'] ?? '') === 'lain')
                            <div class="col-12">
                                @include('livewire.master.partials.unit-picker-lain', ['prefix' => 'rows.'.$i, 'row' => $r, 'opsi' => $opsi, 'satuanLain' => $satuanLain, 'idAwal' => 'row-'.$i])
                            </div>
                        @endif
                    </div>
                @endforeach
                @foreach (['item_id', 'lot_no', 'serial_no', 'piece_length', 'expiry_date', 'qty_received', 'qty_damaged', 'qty_short', 'damage_reason_id', 'uom_id', 'notes', 'purchase_request_order_line_id'] as $f)
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
