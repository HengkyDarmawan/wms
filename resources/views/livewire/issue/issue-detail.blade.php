<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $isu->number }}
                <span class="badge {{ $isu->status->badge() }}">{{ $isu->status->label() }}</span>
                @if ($menunggu) <span class="badge text-bg-warning">{{ __('Menunggu approval') }}</span> @endif
            </h1>
            <p class="text-muted mb-0">
                {{ $isu->project?->code }} — {{ $isu->project?->name }} · {{ __('Gudang Site') }} {{ $isu->warehouse?->code }}
                @if ($isu->reversalOf) · {{ __('Pembalik') }} <a href="{{ route('issues.show', $isu->reversal_of_id) }}">{{ $isu->reversalOf->number }}</a> @endif
                · {{ __('Dibuat') }} {{ $isu->issuer?->name }}
                @if ($isu->submitter) · {{ __('Diajukan') }} {{ $isu->submitter->name }} @endif
                @if ($isu->confirmed_at) · {{ __('Dikonfirmasi') }} {{ $isu->confirmer?->name }} {{ $isu->confirmed_at->format('d/m/Y H:i') }} @endif
                @if ($isu->approver && $isu->status->value === 'confirmed') · {{ __('Disetujui') }} {{ $isu->approver->name }} @endif
            </p>
            @if ($isu->reason) <p class="small mb-0">{{ __('Alasan pembalikan') }}: {{ $isu->reason->label }}</p> @endif
            @if ($isu->notes) <p class="small mb-0">{{ $isu->notes }}</p> @endif
            @if ($isu->rejectReason && $isu->status->value === 'draft')
                <p class="text-danger small mb-0">{{ __('Ditolak') }} {{ $isu->approver?->name }}: {{ $isu->rejectReason->label }} — {{ __('ajukan ulang atau batalkan') }}</p>
            @endif
            @if ($isu->cancelReason) <p class="text-danger small mb-0">{{ __('Dibatalkan') }}: {{ $isu->cancelReason->label }}</p> @endif
            @foreach ($pembalik as $p)
                <p class="small mb-0">{{ __('Pembalik') }} <a href="{{ route('issues.show', $p->id) }}">{{ $p->number }}</a> ({{ $p->status->label() }})</p>
            @endforeach
        </div>
        <div class="d-flex flex-wrap gap-2">
            @include('print.partials.button', ['jenis' => \App\Domain\Template\Enums\DocumentTemplateType::MaterialIssue, 'id' => $isu->id])
            <a class="btn btn-outline-secondary" href="{{ route('issues.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if (in_array($dialog, ['tolak', 'batal', 'balik'], true))
        <div class="card border-warning mb-3">
            <div class="card-header">
                <strong>{{ match ($dialog) { 'tolak' => __('Tolak ISU pembalik'), 'balik' => __('Buat ISU pembalik'), default => __('Batalkan ISU') } }}</strong>
            </div>
            <div class="card-body row g-3">
                @if ($dialog === 'balik')
                    <div class="col-12">
                        <p class="small text-muted mb-2">{{ __('Baris terpilih dikembalikan utuh ke bin asalnya setelah disetujui approver. Satu baris hanya bisa dibalik sekali.') }}</p>
                        @foreach ($lines as $l)
                            @continue($l->movement_id === null || in_array((int) $l->id, $sudahDibalik, true))
                            <div class="form-check">
                                <input class="form-check-input" id="isu-balik-{{ $l->id }}" type="checkbox" wire:model="balik.{{ $l->id }}">
                                <label class="form-check-label" for="isu-balik-{{ $l->id }}">
                                    {{ $l->item?->code }} · {{ $l->bin?->code }} @if ($l->trackingLabel() !== '') · {{ $l->trackingLabel() }} @endif
                                    · {{ number_format((float) $l->qty_base, 2, ',', '.') }} {{ $l->item?->baseUom?->code }}
                                </label>
                            </div>
                        @endforeach
                        @error('form.lines') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label" for="isu-alasan-d">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="isu-alasan-d" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($dialog === 'tolak' ? $alasanTolak : $alasanBatal as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="isu-ket-d">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="isu-ket-d" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="{{ match ($dialog) { 'tolak' => 'tolak', 'balik' => 'buatPembalik', default => 'batalkan' } }}">
                    {{ match ($dialog) { 'tolak' => __('Tolak'), 'balik' => __('Buat pembalik'), default => __('Batalkan') } }}
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
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th scope="col">{{ __('Keperluan') }}</th>
                        <th scope="col">{{ __('Kartu stok') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr>
                            <td>
                                {{ $l->item?->code }}
                                <div class="small text-muted">{{ $l->item?->name }} @if ($l->trackingLabel() !== '') · {{ $l->trackingLabel() }} @endif</div>
                            </td>
                            <td>{{ $l->bin?->code }}</td>
                            <td class="text-end {{ (float) $l->qty_base < 0 ? 'text-danger' : '' }}">
                                {{ number_format((float) $l->qty_base, 2, ',', '.') }} <span class="small text-muted">{{ $l->item?->baseUom?->code }}</span>
                            </td>
                            <td class="small">{{ $l->work_note ?? '—' }}</td>
                            <td class="small">
                                {{ $l->movement_id ? '#'.$l->movement_id : '—' }}
                                @if (in_array((int) $l->id, $sudahDibalik, true)) <span class="badge text-bg-secondary">{{ __('dibalik') }}</span> @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            @can('confirm', $isu)
                <button class="btn btn-primary" type="button" wire:click="konfirmasi" wire:confirm="{{ $isu->isReversal() ? __('Ajukan pembalikan ini ke approval?') : __('Konfirmasi pemakaian? Barang keluar dari stok Gudang Site.') }}">
                    {{ $isu->isReversal() ? __('Ajukan pembalikan') : __('Konfirmasi pemakaian') }}
                </button>
            @endcan
            @can('update', $isu)
                <a class="btn btn-outline-secondary" href="{{ route('issues.edit', $isu) }}">{{ __('Ubah draf') }}</a>
            @endcan
            @can('approve', $isu)
                <button class="btn btn-primary" type="button" wire:click="setujui">{{ __('Setujui') }}</button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">{{ __('Tolak') }}</button>
            @endcan
            @can('cancel', $isu)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">{{ __('Batalkan') }}</button>
            @endcan
            @can('reverse', $isu)
                <button class="btn btn-outline-warning" type="button" wire:click="mintaDialog('balik')">{{ __('Buat ISU pembalik') }}</button>
            @endcan
        </div>
    </div>

    @if ($isu->isReversal())
        @include('approval.partials.history', ['riwayatApproval' => $riwayatApproval])
    @endif

    <div class="card">
        <div class="card-header"><strong>{{ __('Riwayat') }}</strong></div>
        <ul class="list-group list-group-flush small">
            @forelse ($riwayat as $r)
                <li class="list-group-item">{{ $r->created_at?->format('d/m/Y H:i') }} · {{ $r->causer?->name ?? __('Sistem') }} · {{ $r->description }}</li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada riwayat.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
