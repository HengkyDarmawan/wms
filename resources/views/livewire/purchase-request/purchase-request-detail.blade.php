<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $prq->number }}
                <span class="badge {{ $prq->status->badge() }}">{{ $prq->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $prq->origin->label() }}
                @if ($prq->materialRequest) · <a href="{{ route('requests.show', $prq->material_request_id) }}">{{ $prq->materialRequest->number }}</a> @endif
                · {{ __('Gudang tujuan') }} {{ $prq->warehouse?->code }}
                @if ($prq->project) · {{ $prq->project->code }} — {{ $prq->project->name }} @endif
                · {{ __('Dibuat') }} {{ $prq->creator?->name ?? __('Sistem') }}
                @if ($prq->submitter) · {{ __('Diajukan') }} {{ $prq->submitter->name }} @endif
                @if ($prq->approved_at) · {{ __('Disetujui') }} {{ $prq->approver?->name ?? __('otomatis') }} @endif
                @if ($prq->forwarded_at) · {{ __('Diteruskan') }} {{ $prq->forwarder?->name }} {{ $prq->forwarded_at->lokal()->format('d/m/Y') }} @endif
                @if ($prq->fulfilled_at) · {{ __('Dipenuhi') }} {{ $prq->fulfilled_at->lokal()->format('d/m/Y') }} @endif
            </p>
            @if ($prq->notes) <p class="small mb-0">{{ $prq->notes }}</p> @endif
            @if ($prq->rejectReason) <p class="text-danger small mb-0">{{ __('Ditolak') }} {{ $prq->approver?->name }}: {{ $prq->rejectReason->label }}</p> @endif
            @if ($prq->cancelReason) <p class="text-danger small mb-0">{{ __('Dibatalkan') }}: {{ $prq->cancelReason->label }}</p> @endif
            @if ($menunggu) <p class="small mb-0">{{ __('Menunggu keputusan approver.') }}</p> @endif
        </div>
        @if ($prq->status !== \App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus::Draft) <a class="btn btn-outline-secondary" href="{{ route('print.document', ['type' => 'purchase-request', 'id' => $prq->id]) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Cetak') }}</a> @endif
        <a class="btn btn-outline-secondary" href="{{ route('purchase-requests.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if (in_array($dialog, ['tolak', 'batal'], true))
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ $dialog === 'tolak' ? __('Tolak PRQ') : __('Batalkan PRQ') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="prq-alasan-d">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="prq-alasan-d" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($dialog === 'tolak' ? $alasanTolak : $alasanBatal as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="prq-ket-d">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="prq-ket-d" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="{{ $dialog === 'tolak' ? 'tolak' : 'batalkan' }}">{{ $dialog === 'tolak' ? __('Tolak') : __('Batalkan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    @if ($dialog === 'pesan')
        @include('livewire.purchase-request.partials.order-form')
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Baris barang') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th class="text-end" scope="col">{{ __('Diminta') }}</th>
                        <th class="text-end" scope="col">{{ __('Dipesan') }}</th>
                        <th class="text-end" scope="col">{{ __('Diterima') }}</th>
                        <th scope="col">{{ __('Dibutuhkan') }}</th>
                        <th scope="col">{{ __('Keterangan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr>
                            <td>{{ $l->item?->code }} <div class="small text-muted">{{ $l->item?->name }}</div></td>
                            <td class="text-end">{{ number_format((float) $l->qty_base, 2, ',', '.') }} <span class="small text-muted">{{ $l->item?->baseUom?->code }}</span></td>
                            <td class="text-end">{{ number_format((float) $l->qty_ordered, 2, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $l->qty_received, 2, ',', '.') }}</td>
                            <td>{{ $l->required_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="small">{{ $l->notes }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            @can('submit', $prq)
                <button class="btn btn-primary" type="button" wire:click="ajukan" wire:confirm="{{ __('Ajukan PRQ ini?') }}">{{ __('Ajukan') }}</button>
            @endcan
            @can('update', $prq)
                <a class="btn btn-outline-secondary" href="{{ route('purchase-requests.edit', $prq) }}">{{ __('Tinjau draf') }}</a>
            @endcan
            @can('approve', $prq)
                <button class="btn btn-primary" type="button" wire:click="setujui">{{ __('Setujui') }}</button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">{{ __('Tolak') }}</button>
            @endcan
            @if ($prq->status->acceptsOrders() && auth()->user()?->can('po.create') && Route::has('purchase-orders.create'))
                <a class="btn btn-primary" href="{{ route('purchase-orders.create', ['prq' => $prq->id]) }}"><i class="bi bi-receipt-cutoff"></i> {{ __('Buat PO') }}</a>
            @endif
            @can('order', $prq)
                <button class="btn btn-outline-primary" type="button" wire:click="mintaDialog('pesan')">{{ __('Catat pemesanan') }}</button>
            @endcan
            @can('cancel', $prq)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">{{ __('Batalkan') }}</button>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Catatan pemesanan') }}</strong> <span class="small text-muted">{{ __('diisi Penindak Lanjut PR; GRN vendor merujuk baris di sini') }}</span></div>
        <ul class="list-group list-group-flush small">
            @forelse ($orders as $o)
                <li class="list-group-item">
                    <strong>{{ $o->vendor?->name }}</strong>
                    @if ($o->vendor?->status === \App\Domain\Master\Enums\VendorStatus::Provisional) <span class="badge text-bg-warning">{{ __('vendor sementara') }}</span> @endif
                    · @if ($o->purchase_order_id && auth()->user()?->can('po.view'))
                        <a href="{{ route('purchase-orders.show', $o->purchase_order_id) }}">{{ $o->reference() }}</a>
                    @else
                        {{ $o->reference() }}
                    @endif
                    @if ($o->eta_date) · {{ __('ETA') }} {{ $o->eta_date->format('d/m/Y') }} @endif
                    · {{ $o->orderer?->name }} {{ $o->ordered_at?->lokal()->format('d/m/Y H:i') }}
                    @if ($o->vendor_note) <div class="text-muted">{{ $o->vendor_note }}</div> @endif
                    <div>
                        @foreach ($o->lines as $ol)
                            <span class="me-3">{{ $ol->line?->item?->code }}: {{ __('diterima') }} {{ number_format((float) $ol->qty_received, 2, ',', '.') }} / {{ number_format((float) $ol->qty_ordered, 2, ',', '.') }}</span>
                        @endforeach
                    </div>
                </li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada catatan pemesanan.') }}</li>
            @endforelse
            @foreach ($receipts as $g)
                <li class="list-group-item">{{ __('GRN') }} <a href="{{ route('receipts.show', $g->id) }}">{{ $g->number }}</a> · {{ $g->status->label() }} @if ($g->received_at) · {{ $g->received_at->format('d/m/Y') }} @endif</li>
            @endforeach
        </ul>
    </div>

    {{-- A-252: dokumen asal & turunan. --}}
    @unless ($portal ?? false)
        <x-related-documents :document="$prq" />
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
