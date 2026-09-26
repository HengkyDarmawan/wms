<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $wst->number }}
                <span class="badge {{ $wst->status->badge() }}">{{ $wst->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $wst->disposition->label() }} · {{ $wst->project?->code }} — {{ $wst->project?->name }} · {{ __('Gudang') }} {{ $wst->warehouse?->code }}
                @if ($wst->targetBin) · {{ __('ke bin') }} {{ $wst->targetBin->code }} @endif
                · {{ __('Diajukan') }} {{ $wst->submitter?->name }}
                @if ($wst->approver && in_array($wst->status->value, ['approved', 'closed'], true)) · {{ __('Disetujui') }} {{ $wst->approver->name }} @endif
                @if ($wst->closed_at) · {{ __('Ditutup') }} {{ $wst->closer?->name }} {{ $wst->closed_at->lokal()->format('d/m/Y H:i') }} @endif
            </p>
            @if ($wst->notes) <p class="small mb-0">{{ $wst->notes }}</p> @endif
            @if ($wst->rejectReason) <p class="text-danger small mb-0">{{ __('Ditolak') }} {{ $wst->approver?->name }}: {{ $wst->rejectReason->label }}</p> @endif
            @if ($wst->cancelReason) <p class="text-danger small mb-0">{{ __('Dibatalkan') }}: {{ $wst->cancelReason->label }}</p> @endif
            @if ($wst->status->value === 'closed')
                <p class="small mb-0">
                    {{ __('Bukti') }}:
                    @if ($wst->evidence_path) <a href="{{ route('waste-disposals.evidence', $wst) }}" target="_blank" rel="noopener">{{ __('foto berita acara') }}</a> @endif
                    @if ($wst->evidence_note) {{ $wst->evidence_path ? '·' : '' }} {{ $wst->evidence_note }} @endif
                </p>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            @include('print.partials.button', ['jenis' => \App\Domain\Template\Enums\DocumentTemplateType::WasteDisposal, 'id' => $wst->id])
            <a class="btn btn-outline-secondary" href="{{ route('waste-disposals.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if (session('errors')?->any())
        <div class="alert alert-danger" role="alert">
            {{ session('errors')->first() }}
            @if (session('rule')) <span class="badge text-bg-dark ms-1">{{ session('rule') }}</span> @endif
        </div>
    @endif

    @if (in_array($dialog, ['tolak', 'batal'], true))
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ $dialog === 'tolak' ? __('Tolak BA waste') : __('Batalkan BA waste') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="wst-alasan-d">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="wst-alasan-d" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($dialog === 'tolak' ? $alasanTolak : $alasanBatal as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="wst-ket-d">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="wst-ket-d" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="{{ $dialog === 'tolak' ? 'tolak' : 'batalkan' }}">{{ $dialog === 'tolak' ? __('Tolak') : __('Batalkan') }}</button>
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
                        <th scope="col">{{ __('Kartu stok') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr>
                            <td>{{ $l->item?->code }} <div class="small text-muted">{{ $l->item?->name }} @if ($l->trackingLabel() !== '') · {{ $l->trackingLabel() }} @endif</div></td>
                            <td>{{ $l->bin?->code }}</td>
                            <td class="small">{{ $l->stock_status->label() }}</td>
                            <td class="text-end">{{ number_format((float) $l->qty_base, 2, ',', '.') }} <span class="small text-muted">{{ $l->item?->baseUom?->code }}</span></td>
                            <td class="small">{{ $l->reason?->label ?? '—' }}</td>
                            <td class="small">{{ $l->movement_id ? '#'.$l->movement_id : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            @can('approve', $wst)
                <button class="btn btn-primary" type="button" wire:click="setujui">{{ __('Setujui') }}</button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">{{ __('Tolak') }}</button>
            @endcan
            @can('cancel', $wst)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">{{ __('Batalkan') }}</button>
            @endcan
        </div>
    </div>

    @can('close', $wst)
        <form class="card border-primary mb-3" method="POST" action="{{ route('waste-disposals.close', $wst) }}" enctype="multipart/form-data">
            @csrf
            <div class="card-header"><strong>{{ __('Tutup BA waste') }}</strong> <span class="small text-muted">{{ __('bukti wajib: foto berita acara atau nomor BA bertanda tangan') }}</span></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="wst-foto">{{ __('Foto berita acara') }}</label>
                    <input class="form-control" id="wst-foto" name="evidence_photo" type="file" accept="image/jpeg,image/png,image/webp">
                    <div class="form-text">{{ __('JPG/PNG/WebP, maksimal 20 MB; dikecilkan otomatis.') }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="wst-bukti">{{ __('Nomor / keterangan BA') }}</label>
                    <input class="form-control" id="wst-bukti" name="evidence_note" type="text" maxlength="255" value="{{ old('evidence_note') }}" placeholder="{{ __('mis. BA/WST/2026/017 ditandatangani pengepul') }}">
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary" type="submit">{{ $wst->disposition->value === 'reused' ? __('Tutup & kembalikan ke stok') : __('Tutup & keluarkan dari stok') }}</button>
            </div>
        </form>
    @endcan

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
