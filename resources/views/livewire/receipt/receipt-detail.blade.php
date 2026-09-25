<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $grn->number }}
                <span class="badge {{ $grn->status->badge() }}">{{ $grn->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $grn->receipt_type->label() }}: {{ $grn->sourceLabel() }} → {{ $grn->warehouse?->code }}
                @if ($grn->vendor_doc_no) · {{ __('SJ vendor') }} {{ $grn->vendor_doc_no }} @endif
                @if ($grn->received_at) · {{ __('Diterima') }} {{ $grn->received_at->lokal()->format('d/m/Y H:i') }} @endif
            </p>
            @if ($grn->receipt_type->value === 'return' && $grn->goods_return_id)
                <p class="small mb-0">
                    {{ __('Retur') }} <a href="{{ route('returns.show', $grn->goods_return_id) }}">{{ $grn->goodsReturn?->number }}</a>
                    — {{ __('barang masuk bin Retur; GRN selesai otomatis saat barangnya dipilah di detail RET.') }}
                </p>
            @endif
        </div>
        @if ($grn->status !== \App\Domain\Receipt\Enums\GoodsReceiptStatus::Draft) <a class="btn btn-outline-secondary" href="{{ route('print.document', ['type' => 'goods-receipt', 'id' => $grn->id]) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Cetak') }}</a> @endif
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

    @if ($dialog === 'batal')
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ __('Batalkan penerimaan') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="grn-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="grn-alasan" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasanBatal as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="grn-ket">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="grn-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="batalkan">{{ __('Batalkan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    @if ($dialog === 'qc')
        <div class="card border-primary mb-3">
            <div class="card-header"><strong>{{ __('Hasil QC') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="qc-hasil">{{ __('Hasil') }} <span class="wajib">*</span></label>
                    <select class="form-select" id="qc-hasil" wire:model.live="form.qc_result">
                        @foreach ($qcOptions as $nilai => $label)
                            <option value="{{ $nilai }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="qc-alasan">
                        {{ __('Alasan') }} @if ($form['qc_result'] === 'rejected') <span class="wajib">*</span> @endif
                    </label>
                    <select class="form-select @error('form.qc_reason') is-invalid @enderror" id="qc-alasan" wire:model="form.qc_reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasanTolak as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.qc_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="qc-catatan">{{ __('Catatan QC') }}</label>
                    <input class="form-control" id="qc-catatan" type="text" wire:model="form.qc_note" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpanQc">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Baris') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Lot / serial / potongan') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th scope="col">{{ __('Bin terima') }}</th>
                        <th scope="col">{{ __('QC') }}</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr wire:key="grn-line-{{ $l->id }}">
                            <td>{{ $l->item?->code }} <div class="small text-muted">{{ $l->item?->name }}</div></td>
                            <td class="small">
                                {{ $l->trackingLabel() }}
                                @if ($l->expiry_date) <div class="text-muted">{{ __('Kedaluwarsa') }} {{ $l->expiry_date->format('d/m/Y') }}</div> @endif
                            </td>
                            <td class="text-end">
                                {{ number_format((float) $l->qty_received, 2, ',', '.') }}
                                @if ((float) $l->qty_excess > 0)
                                    {{-- BR-GRN-05, A-245: kelebihan tidak diposting GRN, melainkan lewat ADJ. --}}
                                    <div class="small text-warning-emphasis">+{{ number_format((float) $l->qty_excess, 2, ',', '.') }} {{ __('kelebihan → ADJ') }}</div>
                                @endif
                            </td>
                            <td>{{ $l->receivingBin?->code ?? '—' }}</td>
                            <td>
                                @if ($l->qc_result)
                                    <span class="badge {{ $l->qc_result->badge() }}">{{ $l->qc_result->label() }}</span>
                                    @if ($l->qcReason) <div class="small text-muted">{{ $l->qcReason->label }}</div> @endif
                                @elseif ($l->receivingBinIsQuarantine())
                                    <span class="badge text-bg-warning">{{ __('Menunggu QC') }}</span>
                                @else
                                    <span class="text-muted small">{{ __('Tanpa QC') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($l->awaitsQc())
                                    @can('qc', $grn)
                                        <button class="btn btn-sm btn-outline-primary" type="button"
                                                wire:click="mintaDialog('qc', {{ $l->id }})">{{ __('Catat QC') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            @can('update', $grn)
                <a class="btn btn-outline-primary" href="{{ route('receipts.edit', $grn) }}">{{ __('Ubah draf') }}</a>
            @endcan
            @can('receive', $grn)
                <button class="btn btn-primary" type="button" wire:click="terima">{{ __('Terima barang') }}</button>
            @endcan
            @if ($grn->status->value === 'received')
                @can('complete', $grn)
                    <button class="btn btn-success" type="button" wire:click="selesaikan">{{ __('Selesaikan') }}</button>
                @endcan
            @endif
            @if ($bisaReplan)
                @can('complete', $grn)
                    <button class="btn btn-outline-success" type="button" wire:click="buatUlangPutaway">{{ __('Buat ulang put-away') }}</button>
                @endcan
            @endif
            @if (in_array($grn->status->value, ['received', 'completed'], true) && $grn->receipt_type->value === 'vendor')
                @can('create', App\Domain\Receipt\Models\VendorReturn::class)
                    <a class="btn btn-outline-danger" href="{{ route('vendor-returns.create', ['receipt' => $grn->id]) }}">{{ __('Retur ke vendor') }}</a>
                @endcan
            @endif
            @can('cancel', $grn)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">{{ __('Batalkan') }}</button>
            @endcan
        </div>
    </div>

    @if ($crossDock->isNotEmpty())
        <div class="card border-info mb-3">
            <div class="card-header"><strong>{{ __('Ditunggu permintaan material (saran cross-dock)') }}</strong></div>
            <div class="card-body small">
                @foreach ($crossDock as $itemId => $kandidat)
                    @foreach ($kandidat as $k)
                        <div>{{ $k->request?->number }} · {{ $k->displayName() }} · {{ __('dibutuhkan') }} {{ $k->required_date?->format('d/m/Y') }}</div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>{{ __('Tugas put-away') }}</strong></div>
                <ul class="list-group list-group-flush">
                    @forelse ($putaways as $p)
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="{{ route('putaways.show', $p) }}">{{ $p->number }}</a>
                            <span class="badge {{ $p->status->badge() }}">{{ $p->status->label() }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('Belum ada.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>{{ __('Retur ke vendor') }}</strong></div>
                <ul class="list-group list-group-flush">
                    @forelse ($returns as $r)
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="{{ route('vendor-returns.show', $r) }}">{{ $r->number }}</a>
                            <span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('Belum ada.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><strong>{{ __('Riwayat') }}</strong></div>
        <ul class="list-group list-group-flush small">
            @forelse ($riwayat as $a)
                <li class="list-group-item">
                    {{ $a->created_at?->lokal()->format('d/m/Y H:i') }} · {{ $a->causer?->name ?? __('Sistem') }} · {{ $a->description }}
                </li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada riwayat.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
