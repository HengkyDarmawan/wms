<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $cnv->number }}
                <span class="badge {{ $cnv->status->badge() }}">{{ $cnv->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $cnv->conversion_type->label() }} · {{ $cnv->project?->code }} — {{ $cnv->project?->name }} · {{ __('Gudang') }} {{ $cnv->warehouse?->code }}
                @if ($cnv->reversalOf) · {{ __('Pembalik') }} <a href="{{ route('conversions.show', $cnv->reversal_of_id) }}">{{ $cnv->reversalOf->number }}</a> @endif
                · {{ __('Dibuat') }} {{ $cnv->preparer?->name }}
                @if ($cnv->submitter) · {{ __('Diajukan') }} {{ $cnv->submitter->name }} @endif
                @if ($cnv->approver && $cnv->status->value === 'completed') · {{ __('Disetujui') }} {{ $cnv->approver->name }} @endif
                @if ($cnv->completed_at) · {{ __('Selesai') }} {{ $cnv->completer?->name }} {{ $cnv->completed_at->lokal()->format('d/m/Y H:i') }} @endif
            </p>
            @if ($cnv->reason) <p class="small mb-0">{{ __('Alasan pembalikan') }}: {{ $cnv->reason->label }}</p> @endif
            @if ($cnv->notes) <p class="small mb-0">{{ $cnv->notes }}</p> @endif
            @if ($cnv->rejectReason && $cnv->status->value === 'draft')
                <p class="text-danger small mb-0">{{ __('Ditolak') }} {{ $cnv->approver?->name }}: {{ $cnv->rejectReason->label }} — {{ __('perbaiki, ajukan ulang, atau batalkan') }}</p>
            @endif
            @if ($cnv->cancelReason) <p class="text-danger small mb-0">{{ __('Dibatalkan') }}: {{ $cnv->cancelReason->label }}</p> @endif
            @if ($aturan) <p class="small mb-0">{{ __('Aturan approval berlaku') }}: <strong>{{ $aturan }}</strong></p> @endif
            @foreach ($pembalik as $p)
                <p class="small mb-0">{{ __('Pembalik') }} <a href="{{ route('conversions.show', $p->id) }}">{{ $p->number }}</a> ({{ $p->status->label() }})</p>
            @endforeach
        </div>
        <div class="d-flex flex-wrap gap-2">
            @include('print.partials.button', ['jenis' => \App\Domain\Template\Enums\DocumentTemplateType::Conversion, 'id' => $cnv->id])
            <a class="btn btn-outline-secondary" href="{{ route('conversions.index') }}">{{ __('Kembali') }}</a>
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
                <strong>{{ match ($dialog) { 'tolak' => __('Tolak CNV'), 'balik' => __('Buat CNV pembalik'), default => __('Batalkan CNV') } }}</strong>
            </div>
            <div class="card-body row g-3">
                @if ($dialog === 'balik')
                    <p class="col-12 small text-muted mb-0">{{ __('Semua hasil dikeluarkan lagi dan input dikembalikan ke binnya. Hanya bisa bila semua output, offcut, dan waste masih utuh di bin dan belum dipakai dokumen lain.') }}</p>
                @endif
                <div class="col-md-6">
                    <label class="form-label" for="cnv-alasan-d">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="cnv-alasan-d" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($dialog === 'tolak' ? $alasanTolak : $alasanBatal as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cnv-ket-d">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="cnv-ket-d" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
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

    <div class="card mb-3 border-primary">
        <div class="card-body py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fs-5 mb-0">{{ $kalimat }}</span>
            @if ((float) $cnv->total_waste > 0 && $cnv->status->value === 'completed' && auth()->user()?->can('waste.create'))
                <a class="btn btn-sm btn-outline-danger" href="{{ route('waste-disposals.create', ['warehouse' => $cnv->warehouse_id, 'project' => $cnv->project_id]) }}"><i class="bi bi-trash3"></i> {{ __('Buat BA Waste') }}</a>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([[__('Input'), $cnv->total_input], [__('Output'), $cnv->total_output], [__('Offcut (sisa layak)'), $cnv->total_offcut], [__('Waste (sisa buang)'), $cnv->total_waste], [__('Kerf (rugi potong)'), $cnv->total_kerf]] as [$label, $nilai])
            <div class="col-6 col-md">
                <div class="card h-100"><div class="card-body py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fs-5">{{ \App\Domain\Conversion\Support\ConversionPlanner::angka((float) $nilai) }} <span class="small text-muted">{{ $uom }}</span></div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Input') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Bin') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th scope="col">{{ __('Kartu stok') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($inputs as $l)
                        <tr>
                            <td>{{ $l->item?->code }} <div class="small text-muted">{{ $l->item?->name }} @if ($l->trackingLabel() !== '') · {{ $l->trackingLabel() }} @endif</div></td>
                            <td>{{ $l->bin?->code }}</td>
                            <td class="text-end">{{ \App\Domain\Conversion\Support\ConversionPlanner::angka((float) $l->qty_base) }} <span class="small text-muted">{{ $l->item?->baseUom?->code }}</span></td>
                            <td class="small">{{ $l->movement_id ? '#'.$l->movement_id : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Hasil') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Jenis') }}</th>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Bin') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th scope="col">{{ __('Silsilah') }}</th>
                        <th scope="col">{{ __('Kartu stok') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($outputs as $o)
                        <tr>
                            <td>
                                {{ $o->output_kind->label() }}
                                @if ($o->auto_waste) <span class="badge text-bg-secondary" title="{{ __('Sisa di bawah panjang minimum offcut, otomatis menjadi waste') }}">{{ __('otomatis') }}</span> @endif
                                @if ($o->reason) <div class="small text-muted">{{ $o->reason->label }}</div> @endif
                            </td>
                            <td>{{ $o->item?->code }} <div class="small text-muted">{{ $o->item?->name }} @if ($o->trackingLabel() !== '') · {{ $o->trackingLabel() }} @endif</div></td>
                            <td>{{ $o->bin?->code ?? '—' }}</td>
                            <td class="text-end">{{ \App\Domain\Conversion\Support\ConversionPlanner::angka((float) $o->qty_base) }} <span class="small text-muted">{{ $o->item?->baseUom?->code }}</span></td>
                            <td class="small">
                                @if ($o->parentInput)
                                    {{ __('dari') }} {{ $o->parentInput->item?->code }} {{ $o->parentInput->piece?->piece_no }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small">{{ $o->movement_id ? '#'.$o->movement_id : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            @can('complete', $cnv)
                <button class="btn btn-primary" type="button" wire:click="selesaikan" wire:confirm="{{ $cnv->isReversal() ? __('Selesaikan pembalikan? Hasil keluar dan input kembali ke stok.') : __('Selesaikan konversi? Input keluar dan hasil masuk stok.') }}">
                    {{ $cnv->isReversal() ? __('Selesaikan pembalikan') : __('Selesaikan konversi') }}
                </button>
            @endcan
            @can('submit', $cnv)
                <button class="btn btn-primary" type="button" wire:click="ajukan" wire:confirm="{{ __('Ajukan CNV ini ke approval?') }}">{{ __('Ajukan ke approval') }}</button>
            @endcan
            @can('update', $cnv)
                <a class="btn btn-outline-secondary" href="{{ route('conversions.edit', $cnv) }}">{{ __('Ubah draf') }}</a>
            @endcan
            @can('approve', $cnv)
                <button class="btn btn-primary" type="button" wire:click="setujui">{{ __('Setujui') }}</button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">{{ __('Tolak') }}</button>
            @endcan
            @can('cancel', $cnv)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">{{ __('Batalkan') }}</button>
            @endcan
            @can('reverse', $cnv)
                <button class="btn btn-outline-warning" type="button" wire:click="mintaDialog('balik')">{{ __('Buat CNV pembalik') }}</button>
            @endcan
        </div>
    </div>

    {{-- A-252: dokumen asal & turunan. --}}
    @unless ($portal ?? false)
        <x-related-documents :document="$cnv" />
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
