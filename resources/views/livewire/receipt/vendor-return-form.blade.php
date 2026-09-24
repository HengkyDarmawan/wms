<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('RTV baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Hanya barang di Karantina dengan hasil QC Ditolak atau Karantina yang bisa diretur.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('vendor-returns.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label" for="rtv-grn">{{ __('GRN asal') }} <span class="wajib">*</span></label>
                <select class="form-select @error('receiptId') is-invalid @enderror" id="rtv-grn" wire:model.live="receiptId">
                    <option value="">{{ __('Pilih GRN…') }}</option>
                    @foreach ($receipts as $g)
                        <option value="{{ $g->id }}">{{ $g->number }} — {{ $g->vendor?->name }}</option>
                    @endforeach
                </select>
                @error('receiptId') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="rtv-ket">{{ __('Keterangan') }}</label>
                <input class="form-control" id="rtv-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Hasil QC') }}</th>
                        <th class="text-end" scope="col">{{ __('Diterima') }}</th>
                        <th scope="col">{{ __('Jumlah retur') }}</th>
                        <th scope="col">{{ __('Alasan') }} <span class="wajib">*</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $l)
                        <tr wire:key="rtv-line-{{ $l->id }}">
                            <td>{{ $l->item?->code }} <div class="small text-muted">{{ $l->trackingLabel() }}</div></td>
                            <td><span class="badge {{ $l->qc_result->badge() }}">{{ $l->qc_result->label() }}</span></td>
                            <td class="text-end">{{ number_format((float) $l->qty_received, 2, ',', '.') }}</td>
                            <td>
                                <input class="form-control form-control-sm" type="number" step="0.0001" min="0"
                                       wire:model="isian.{{ $l->id }}.qty">
                            </td>
                            <td>
                                <select class="form-select form-select-sm" wire:model="isian.{{ $l->id }}.reason">
                                    <option value="">{{ __('Pilih alasan…') }}</option>
                                    @foreach ($alasan as $kode => $label)
                                        <option value="{{ $kode }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="5">{{ __('Pilih GRN yang punya barang ditolak atau dikarantina.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @foreach (['qty_base', 'reason_code_id'] as $f)
            @error('form.'.$f) <div class="text-danger small px-3">{{ $message }}</div> @enderror
        @endforeach
    </div>

    <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Ajukan RTV') }}</button>
</div>
