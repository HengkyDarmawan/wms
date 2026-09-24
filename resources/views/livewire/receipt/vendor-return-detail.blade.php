<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $rtv->number }}
                <span class="badge {{ $rtv->status->badge() }}">{{ $rtv->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $rtv->warehouse?->code }} → {{ $rtv->vendor?->name }} · GRN
                <a href="{{ route('receipts.show', $rtv->goods_receipt_id) }}">{{ $rtv->receipt?->number }}</a>
                · {{ __('Diajukan') }} {{ $rtv->submitter?->name }}
                @if ($rtv->approver) · {{ __('Diputus') }} {{ $rtv->approver->name }} @endif
                @if ($rtv->replacementReceipt) · {{ __('Pengganti') }} {{ $rtv->replacementReceipt->number }} @endif
            </p>
            @if ($rtv->rejectReason) <p class="text-danger small mb-0">{{ __('Ditolak') }}: {{ $rtv->rejectReason->label }}</p> @endif
            @if ($rtv->cancelReason) <p class="text-danger small mb-0">{{ __('Dibatalkan') }}: {{ $rtv->cancelReason->label }}</p> @endif
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('vendor-returns.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if (in_array($dialog, ['tolak', 'batal'], true))
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ $dialog === 'tolak' ? __('Tolak RTV') : __('Batalkan RTV') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="rtv-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="rtv-alasan" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($dialog === 'tolak' ? $alasanTolak : $alasanBatal as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="rtv-ket2">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="rtv-ket2" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
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
                        <th scope="col">{{ __('Bin') }}</th>
                        <th scope="col">{{ __('Kondisi') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th scope="col">{{ __('Alasan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr>
                            <td>
                                {{ $l->item?->code }}
                                <div class="small text-muted">{{ $l->lot?->lot_no ?? $l->serial?->serial_no ?? $l->piece?->piece_no ?? $l->item?->name }}</div>
                            </td>
                            <td>{{ $l->bin?->code }}</td>
                            <td><span class="badge text-bg-{{ $l->stock_status->badge() }}">{{ $l->stock_status->label() }}</span></td>
                            <td class="text-end">{{ number_format((float) $l->qty_base, 2, ',', '.') }}</td>
                            <td>{{ $l->reason?->label }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            @can('approve', $rtv)
                <button class="btn btn-primary" type="button" wire:click="setujui">{{ __('Setujui') }}</button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">{{ __('Tolak') }}</button>
            @endcan
            @can('ship', $rtv)
                <button class="btn btn-warning" type="button" wire:click="kirim">{{ __('Kirim ke vendor') }}</button>
            @endcan
            @can('complete', $rtv)
                <button class="btn btn-success" type="button" wire:click="selesaikan">{{ __('Konfirmasi vendor — selesai') }}</button>
            @endcan
            @can('cancel', $rtv)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">{{ __('Batalkan') }}</button>
            @endcan
        </div>
    </div>

    @include('approval.partials.history', ['riwayatApproval' => $riwayatApproval])

    <div class="card">
        <div class="card-header"><strong>{{ __('Riwayat') }}</strong></div>
        <ul class="list-group list-group-flush small">
            @forelse ($riwayat as $a)
                <li class="list-group-item">{{ $a->created_at?->format('d/m/Y H:i') }} · {{ $a->causer?->name ?? __('Sistem') }} · {{ $a->description }}</li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada riwayat.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
