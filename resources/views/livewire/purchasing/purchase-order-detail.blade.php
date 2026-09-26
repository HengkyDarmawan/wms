<div>
@php($rp = fn ($n) => \App\Domain\Purchasing\Support\Money::format($n))
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $po->number }}
                <span class="badge {{ $po->status->badge() }}">{{ $po->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $po->vendor?->name }} ({{ $po->vendor?->vendor_type?->label() }})
                · {{ __('Gudang tujuan') }} {{ $po->warehouse?->code }}
                · {{ __('Tanggal PO') }} {{ $po->order_date?->format('d/m/Y') }}
                @if ($po->eta_date) · {{ __('ETA') }} {{ $po->eta_date->format('d/m/Y') }} @endif
                · {{ __('Dibuat') }} {{ $po->creator?->name }}
                @if ($po->submitter) · {{ __('Diajukan') }} {{ $po->submitter->name }} @endif
                @if ($po->approved_at && $po->status !== \App\Domain\Purchasing\Enums\PurchaseOrderStatus::Rejected) · {{ __('Disetujui') }} {{ $po->approver?->name ?? __('otomatis') }} @endif
            </p>
            @if ($po->payment_terms) <p class="small mb-0">{{ __('Termin') }}: {{ $po->payment_terms }}</p> @endif
            @if ($po->notes) <p class="small mb-0">{{ $po->notes }}</p> @endif
            @if ($po->rejectReason) <p class="text-danger small mb-0">{{ __('Ditolak') }} {{ $po->approver?->name }}: {{ $po->rejectReason->label }}</p> @endif
            @if ($po->cancelReason) <p class="text-danger small mb-0">{{ __('Dibatalkan') }}: {{ $po->cancelReason->label }}</p> @endif
            @if ($po->closeReason) <p class="small mb-0">{{ __('Ditutup dengan sisa') }}: {{ $po->closeReason->label }}</p> @endif
            @if ($menunggu) <p class="small mb-0">{{ __('Menunggu keputusan approver.') }}</p> @endif
        </div>
        <div class="d-flex gap-2">
            @if ($po->status !== \App\Domain\Purchasing\Enums\PurchaseOrderStatus::Draft)
                <a class="btn btn-outline-secondary" href="{{ route('print.document', ['type' => 'purchase-order', 'id' => $po->id]) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Cetak PO') }}</a>
            @endif
            <a class="btn btn-outline-secondary" href="{{ route('purchase-orders.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if (in_array($dialog, ['tolak', 'batal', 'tutup'], true))
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ ['tolak' => __('Tolak PO'), 'batal' => __('Batalkan PO'), 'tutup' => __('Tutup sisa PO')][$dialog] }}</strong></div>
            <div class="card-body row g-3">
                @if ($dialog === 'tutup')
                    <p class="col-12 small mb-0">{{ __('Jumlah yang belum datang ditutup dan dilepas dari Purchase Request sehingga bisa dipesan lagi.') }}</p>
                @endif
                <div class="col-md-6">
                    <label class="form-label" for="po-alasan-d">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="po-alasan-d" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($dialog === 'tolak' ? $alasanTolak : $alasanBatal as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="po-ket-d">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="po-ket-d" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="{{ ['tolak' => 'tolak', 'batal' => 'batalkan', 'tutup' => 'tutupSisa'][$dialog] }}">{{ ['tolak' => __('Tolak'), 'batal' => __('Batalkan'), 'tutup' => __('Tutup sisa')][$dialog] }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    @if ($dialog === 'eta')
        <div class="card border-primary mb-3">
            <div class="card-header"><strong>{{ __('Ubah perkiraan datang') }}</strong></div>
            <div class="card-body">
                <label class="form-label" for="po-eta-d">{{ __('Perkiraan datang') }}</label>
                <input class="form-control @error('form.eta_date') is-invalid @enderror" id="po-eta-d" type="date" wire:model="form.eta_date" style="max-width: 14rem">
                @error('form.eta_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpanEta">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Baris PO') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('PRQ') }}</th>
                        <th class="text-end" scope="col">{{ __('Dipesan') }}</th>
                        <th class="text-end" scope="col">{{ __('Diterima') }}</th>
                        <th class="text-end" scope="col">{{ __('Ditutup') }}</th>
                        <th class="text-end" scope="col">{{ __('Harga satuan') }}</th>
                        <th class="text-end" scope="col">{{ __('Nilai') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr>
                            <td>{{ $l->item?->code }} <div class="small text-muted">{{ $l->item?->name }}</div></td>
                            <td class="small">
                                @if ($l->requestLine?->purchaseRequest)
                                    <a href="{{ route('purchase-requests.show', $l->requestLine->purchase_request_id) }}">{{ $l->requestLine->purchaseRequest->number }}</a>
                                @endif
                            </td>
                            <td class="text-end">
                                {{ number_format((float) $l->qty_base, 2, ',', '.') }} <span class="small text-muted">{{ $l->item?->baseUom?->code }}</span>
                                @if ((float) $l->qty_over_request > 0)
                                    <div class="small text-warning-emphasis">{{ __('Lebih :n dari permintaan', ['n' => number_format((float) $l->qty_over_request, 2, ',', '.')]) }}: {{ $l->over_order_reason }}</div>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $l->qty_received, 2, ',', '.') }}</td>
                            <td class="text-end">{{ (float) $l->qty_cancelled > 0 ? number_format((float) $l->qty_cancelled, 2, ',', '.') : '—' }}</td>
                            <td class="text-end text-nowrap">{{ $rp($l->unit_price) }}</td>
                            <td class="text-end text-nowrap">{{ $rp($l->line_amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th class="text-end" colspan="6">{{ __('Nilai PO') }}</th>
                        <th class="text-end text-nowrap">{{ $rp($po->total_amount) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            @can('update', $po)
                <a class="btn btn-outline-secondary" href="{{ route('purchase-orders.edit', $po) }}">{{ __('Ubah draf') }}</a>
            @endcan
            @can('submit', $po)
                <button class="btn btn-primary" type="button" wire:click="ajukan" wire:confirm="{{ __('Ajukan PO ini?') }}">{{ __('Ajukan') }}</button>
            @endcan
            @can('approve', $po)
                <button class="btn btn-primary" type="button" wire:click="setujui">{{ __('Setujui') }}</button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">{{ __('Tolak') }}</button>
            @endcan
            @can('updateEta', $po)
                <button class="btn btn-outline-primary" type="button" wire:click="mintaDialog('eta')">{{ __('Ubah ETA') }}</button>
            @endcan
            @can('close', $po)
                <button class="btn btn-outline-warning" type="button" wire:click="mintaDialog('tutup')">{{ __('Tutup sisa') }}</button>
            @endcan
            @can('cancel', $po)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">{{ __('Batalkan') }}</button>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Terkait di gudang') }}</strong> <span class="small text-muted">{{ __('PRQ asal dan GRN yang menerima barang PO ini') }}</span></div>
        <ul class="list-group list-group-flush small">
            @foreach ($prqs as $p)
                <li class="list-group-item">{{ __('PRQ') }} <a href="{{ route('purchase-requests.show', $p->id) }}">{{ $p->number }}</a></li>
            @endforeach
            @forelse ($receipts as $g)
                <li class="list-group-item">{{ __('GRN') }} <a href="{{ route('receipts.show', $g->id) }}">{{ $g->number }}</a> · {{ $g->status->label() }} @if ($g->received_at) · {{ $g->received_at->format('d/m/Y') }} @endif</li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada barang diterima.') }}</li>
            @endforelse
        </ul>
    </div>

    {{-- A-252: dokumen asal & turunan. --}}
    @unless ($portal ?? false)
        <x-related-documents :document="$po" />
    @endunless
    @include('approval.partials.history', ['riwayatApproval' => $riwayatApproval])

    <div class="card">
        <div class="card-header"><strong>{{ __('Riwayat') }}</strong></div>
        <ul class="list-group list-group-flush small">
            @forelse ($riwayat as $r)
                <li class="list-group-item">{{ $r->created_at?->lokal()->format('d/m/Y H:i') }} · {{ $r->causer?->name ?? __('Sistem') }} · {{ $r->description }}</li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada riwayat.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
