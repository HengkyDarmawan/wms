<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $task->number }}
                <span class="badge {{ $task->status->badge() }}">{{ $task->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $task->warehouse?->code }} · {{ __('dari') }}
                <a href="{{ route('receipts.show', $task->goods_receipt_id) }}">{{ $task->receipt?->number }}</a>
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('putaways.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @foreach ($peringatan as $p)
        <div class="alert alert-warning" role="alert">{{ $p }}</div>
    @endforeach

    @if ($dialog === 'batal')
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ __('Batalkan tugas put-away') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="put-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="put-alasan" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasan as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="put-ket">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="put-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="batalkan">{{ __('Batalkan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th scope="col">{{ __('Dari') }}</th>
                        <th scope="col">{{ __('Saran') }}</th>
                        <th scope="col">{{ __('Bin tujuan') }} <span class="wajib">*</span></th>
                        <th scope="col">{{ __('Alasan ganti bin') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr wire:key="put-line-{{ $l->id }}">
                            <td>
                                {{ $l->item?->code }}
                                <div class="small text-muted">
                                    {{ $l->lot?->lot_no ?? $l->serial?->serial_no ?? $l->piece?->piece_no ?? $l->item?->name }}
                                </div>
                            </td>
                            <td class="text-end">{{ number_format((float) $l->qty_base, 2, ',', '.') }}</td>
                            <td>{{ $l->fromBin?->code }}</td>
                            <td>{{ $l->suggestedBin?->code ?? '—' }}</td>
                            <td>
                                @if ($task->status->value === 'pending')
                                    <input class="form-control form-control-sm mb-1" type="text" data-scan
                                           aria-label="{{ __('Pindai kode bin') }}" placeholder="{{ __('Pindai kode bin') }}"
                                           wire:change="pindaiBin({{ $l->id }}, $event.target.value)"
                                           wire:keydown.enter.prevent="pindaiBin({{ $l->id }}, $event.target.value)">
                                    @error('pindai.'.$l->id) <div class="small text-danger">{{ $message }}</div> @enderror
                                    <select class="form-select form-select-sm" wire:model="isian.{{ $l->id }}.bin_id">
                                        <option value="">{{ __('Pilih bin…') }}</option>
                                        @foreach ($bins as $b)
                                            <option value="{{ $b->id }}">{{ $b->code }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    {{ $l->bin?->code ?? '—' }}
                                @endif
                            </td>
                            <td>
                                @if ($task->status->value === 'pending')
                                    <input class="form-control form-control-sm" type="text"
                                           wire:model="isian.{{ $l->id }}.override_reason" placeholder="{{ __('Bila berbeda dari saran') }}">
                                @else
                                    <span class="small">{{ $l->override_reason }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @foreach (['bin_id', 'override_reason'] as $f)
            @error('form.'.$f) <div class="text-danger small px-3">{{ $message }}</div> @enderror
        @endforeach
        <div class="card-footer d-flex gap-2">
            @can('complete', $task)
                <button class="btn btn-success" type="button" wire:click="selesaikan">{{ __('Selesaikan put-away') }}</button>
            @endcan
            @can('cancel', $task)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaBatal">{{ __('Batalkan') }}</button>
            @endcan
        </div>
    </div>
</div>
