<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $trf->number }}
                <span class="badge {{ $trf->status->badge() }}">{{ $trf->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $trf->fromWarehouse?->code }}@if ($trf->fromProject) ({{ $trf->fromProject->code }})@endif
                → {{ $trf->toWarehouse?->code }}@if ($trf->toProject) ({{ $trf->toProject->code }})@endif
                · {{ $trf->kind()->label() }} · {{ $trf->origin->label() }}
                @if ($req) · <a href="{{ route('requests.show', $req) }}">{{ $req->number }}</a> @endif
                · {{ __('Diajukan') }} {{ $trf->submitter?->name ?? __('Sistem') }}
                @if ($trf->approver) · {{ __('Diputus') }} {{ $trf->approver->name }} @endif
                @if ($trf->completed_at) · {{ __('Selesai') }} {{ $trf->completed_at->lokal()->format('d/m/Y H:i') }} @endif
            </p>
            @if ($trf->notes) <p class="small mb-0">{{ $trf->notes }}</p> @endif
            @if ($trf->rejectReason) <p class="text-danger small mb-0">{{ __('Ditolak') }}: {{ $trf->rejectReason->label }}</p> @endif
            @if ($trf->cancelReason) <p class="text-danger small mb-0">{{ __('Dibatalkan') }}: {{ $trf->cancelReason->label }}</p> @endif
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('transfers.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if (in_array($dialog, ['tolak', 'batal'], true))
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ $dialog === 'tolak' ? __('Tolak TRF') : __('Batalkan TRF') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="trf-alasan-d">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="trf-alasan-d" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($dialog === 'tolak' ? $alasanTolak : $alasanBatal as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="trf-ket-d">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="trf-ket-d" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="{{ $dialog === 'tolak' ? 'tolak' : 'batalkan' }}">
                    {{ $dialog === 'tolak' ? __('Tolak') : __('Batalkan') }}
                </button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th class="text-end" scope="col">{{ __('Dikirim') }}</th>
                        <th class="text-end" scope="col">{{ __('Diterima') }}</th>
                        <th scope="col">{{ __('Baris REQ') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr>
                            <td>{{ $l->item?->code }} <div class="small text-muted">{{ $l->item?->name }} @if ($l->notes) · {{ $l->notes }} @endif</div></td>
                            <td class="text-end">{{ number_format((float) $l->qty_base, 2, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $l->qty_shipped, 2, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $l->qty_received, 2, ',', '.') }}</td>
                            <td class="small">{{ $l->requestLine?->request?->number ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            @can('approve', $trf)
                <button class="btn btn-primary" type="button" wire:click="setujui">{{ __('Setujui') }}</button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">{{ __('Tolak') }}</button>
            @endcan
            @can('createPick', $trf)
                <button class="btn btn-outline-primary" type="button" wire:click="buatPicking">{{ __('Buat tugas picking') }}</button>
            @endcan
            @if ($pckSiapKirim)
                @can('create', App\Domain\Shipment\Models\Shipment::class)
                    <a class="btn btn-outline-primary" href="{{ route('shipments.create', ['pick_task' => $pckSiapKirim->id]) }}">{{ __('Susun surat jalan') }}</a>
                @endcan
            @endif
            @if ($sjMenungguGrn)
                @can('create', App\Domain\Receipt\Models\GoodsReceipt::class)
                    <a class="btn btn-outline-primary" href="{{ route('receipts.create', ['shipment' => $sjMenungguGrn->id]) }}">{{ __('Terima di gudang tujuan') }}</a>
                @endcan
            @endif
            @can('cancel', $trf)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">{{ __('Batalkan') }}</button>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Pergerakan fisik') }}</strong></div>
        <ul class="list-group list-group-flush small">
            @forelse ($pickTasks as $p)
                <li class="list-group-item">{{ __('Tugas picking') }} <a href="{{ route('picks.show', $p->id) }}">{{ $p->number }}</a> · {{ $p->warehouse?->code }} · {{ $p->status->label() }}</li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada tugas picking.') }}</li>
            @endforelse
            @foreach ($shipments as $s)
                <li class="list-group-item">{{ __('Surat jalan') }} <a href="{{ route('shipments.show', $s->id) }}">{{ $s->number }}</a> · {{ $s->shipment_method->label() }} · {{ $s->status->label() }}</li>
            @endforeach
            @foreach ($receipts as $g)
                <li class="list-group-item">{{ __('Penerimaan') }} <a href="{{ route('receipts.show', $g->id) }}">{{ $g->number }}</a> · {{ $g->status->label() }}</li>
            @endforeach
        </ul>
    </div>

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
