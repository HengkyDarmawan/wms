<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $adj->number }}
                <span class="badge {{ $adj->status->badge() }}">{{ $adj->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $adj->warehouse?->code }} · {{ $adj->origin->label() }}
                @if ($adj->stockCount) · <a href="{{ route('counts.show', $adj->stock_count_id) }}">{{ $adj->stockCount->number }}</a> @endif
                @if ($adj->reversalOf) · {{ __('Pembalik') }} <a href="{{ route('adjustments.show', $adj->reversal_of_id) }}">{{ $adj->reversalOf->number }}</a> @endif
                · {{ __('Alasan') }}: {{ $adj->reason?->label }}
                · {{ __('Diajukan') }} {{ $adj->submitter?->name }}
                @if ($adj->approver) · {{ __('Diputus') }} {{ $adj->approver->name }} @endif
                @if ($adj->posted_at) · {{ __('Diposting') }} {{ $adj->posted_at->lokal()->format('d/m/Y H:i') }} @endif
            </p>
            @if ($adj->notes) <p class="small mb-0">{{ $adj->notes }}</p> @endif
            @if ($adj->rejectReason) <p class="text-danger small mb-0">{{ __('Ditolak') }}: {{ $adj->rejectReason->label }}</p> @endif
            @if ($adj->cancelReason) <p class="text-danger small mb-0">{{ __('Dibatalkan') }}: {{ $adj->cancelReason->label }}</p> @endif
            @if ($pembalik) <p class="small mb-0">{{ __('Dibalik oleh') }} <a href="{{ route('adjustments.show', $pembalik) }}">{{ $pembalik->number }}</a></p> @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            @include('print.partials.button', ['jenis' => \App\Domain\Template\Enums\DocumentTemplateType::StockAdjustment, 'id' => $adj->id, 'teks' => __('Cetak BA')])
            <a class="btn btn-outline-secondary" href="{{ route('adjustments.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if (in_array($dialog, ['tolak', 'batal'], true))
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ $dialog === 'tolak' ? __('Tolak ADJ') : __('Batalkan ADJ') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="adj-alasan-d">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="adj-alasan-d" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($dialog === 'tolak' ? $alasanTolak : $alasanBatal as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="adj-ket-d">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="adj-ket-d" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
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
                        <th scope="col">{{ __('Bin') }}</th>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Kondisi') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah ±') }}</th>
                        <th scope="col">{{ __('Kartu stok') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr>
                            <td>{{ $l->bin?->code }}</td>
                            <td>
                                {{ $l->item?->code }}
                                <div class="small text-muted">{{ $l->trackingLabel() ?: $l->item?->name }} @if ($l->notes) · {{ $l->notes }} @endif</div>
                            </td>
                            <td><span class="badge text-bg-{{ $l->stock_status->badge() }}">{{ $l->stock_status->label() }}</span></td>
                            <td class="text-end {{ (float) $l->qty_delta < 0 ? 'text-danger' : 'text-success' }}">
                                {{ (float) $l->qty_delta > 0 ? '+' : '' }}{{ number_format((float) $l->qty_delta, 2, ',', '.') }}
                            </td>
                            <td class="small">{{ $l->movement_id ? '#'.$l->movement_id : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            @can('approve', $adj)
                <button class="btn btn-primary" type="button" wire:click="setujui">{{ __('Setujui') }}</button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">{{ __('Tolak') }}</button>
            @endcan
            @can('cancel', $adj)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">{{ __('Batalkan') }}</button>
            @endcan
            @can('reverse', $adj)
                <a class="btn btn-outline-warning" href="{{ route('adjustments.create', ['reversal_of' => $adj->id]) }}">{{ __('Buat ADJ pembalik') }}</a>
            @endcan
        </div>
    </div>

    @if ($adj->isManual())
        @include('approval.partials.history', ['riwayatApproval' => $riwayatApproval])
    @endif

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
