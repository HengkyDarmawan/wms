<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $ret->number }}
                <span class="badge {{ $ret->status->badge() }}">{{ $ret->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $ret->project?->code }} — {{ $ret->project?->name }}
                · {{ __('ke') }} {{ $ret->toWarehouse?->code }}
                · {{ $ret->self_delivered ? __('Diantar sendiri') : ($ret->isPickup() ? __('Dijemput driver') : __('SJ balik dari').' '.$ret->fromWarehouse?->code) }}
                @if ($ret->originShipment) · {{ __('SJ asal') }} {{ $ret->originShipment->number }} @endif
                · {{ __('Diajukan') }} {{ $ret->requester?->name }}
                @if ($ret->approver) · {{ __('Diputus') }} {{ $ret->approver->name }} @endif
                @if ($ret->sorted_at) · {{ __('Dipilah') }} {{ $ret->sorter?->name }} {{ $ret->sorted_at->lokal()->format('d/m/Y H:i') }} @endif
            </p>
            @if ($ret->notes) <p class="small mb-0">{{ $ret->notes }}</p> @endif
            @if ($ret->rejectReason) <p class="text-danger small mb-0">{{ __('Ditolak') }}: {{ $ret->rejectReason->label }}</p> @endif
            @if ($ret->cancelReason) <p class="text-danger small mb-0">{{ __('Dibatalkan') }}: {{ $ret->cancelReason->label }}</p> @endif
        </div>
        @if ($rute === 'returns') <a class="btn btn-outline-secondary" href="{{ route('print.document', ['type' => 'goods-return', 'id' => $ret->id]) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Cetak') }}</a> @endif
        <a class="btn btn-outline-secondary" href="{{ route($rute.'.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if (in_array($dialog, ['tolak', 'batal'], true))
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ $dialog === 'tolak' ? __('Tolak RET') : __('Batalkan RET') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="ret-alasan-d">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="ret-alasan-d" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($dialog === 'tolak' ? $alasanTolak : $alasanBatal as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ret-ket-d">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="ret-ket-d" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
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
                        <th scope="col">{{ __('Asal') }}</th>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Kepemilikan') }}</th>
                        <th class="text-end" scope="col">{{ __('Diajukan') }}</th>
                        <th class="text-end" scope="col">{{ __('Diterima') }}</th>
                        <th scope="col">{{ __('Hasil pilah') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr @class(['table-light' => $l->isSplit()])>
                            <td class="small">{{ $l->isSplit() ? '↳ '.__('hasil pilah') : $l->source()->label() }} @if ($l->fromBin) <div class="text-muted">{{ $l->fromBin->code }}</div> @endif</td>
                            <td>{{ $l->item?->code }} <div class="small text-muted">{{ $l->item?->name }} @if ($l->trackingLabel()) · {{ $l->trackingLabel() }} @endif</div></td>
                            <td class="small">{{ $l->ownership->label() }} <div class="text-muted">{{ $l->stock_status->label() }}</div></td>
                            <td class="text-end">{{ $l->isSplit() ? '—' : number_format((float) $l->qty_base, 2, ',', '.') }}</td>
                            <td class="text-end">{{ $l->isSplit() ? '—' : number_format((float) $l->qty_received, 2, ',', '.') }}</td>
                            <td class="small">
                                @if ($l->sorting)
                                    <span class="badge {{ $l->sorting->badge() }}">{{ $l->sorting->label() }}</span>
                                    {{ number_format((float) $l->sorted_qty, 2, ',', '.') }} → {{ $l->targetBin?->code }}
                                    @if ($l->newPiece) · {{ __('potongan baru') }} {{ $l->newPiece->piece_no }} @endif
                                    @if ($l->reason) <div class="text-muted">{{ $l->reason->label }}</div> @endif
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            @can('approve', $ret)
                <button class="btn btn-primary" type="button" wire:click="setujui">{{ __('Setujui') }}</button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">{{ __('Tolak') }}</button>
            @endcan
            @can('createPick', $ret)
                <button class="btn btn-outline-primary" type="button" wire:click="buatPicking">{{ __('Buat tugas picking SJ balik') }}</button>
            @endcan
            @if (! $portal && $pck && $pck->status->value === 'completed' && ! $ret->return_shipment_id)
                @can('create', App\Domain\Shipment\Models\Shipment::class)
                    <a class="btn btn-outline-primary" href="{{ route('shipments.create', ['pick_task' => $pck->id]) }}">{{ __('Susun SJ balik') }}</a>
                @endcan
            @endif
            @can('receive', $ret)
                <button class="btn btn-outline-primary" type="button" wire:click="terima">{{ __('Terima retur (GRN)') }}</button>
            @endcan
            @can('cancel', $ret)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">{{ __('Batalkan') }}</button>
            @endcan
        </div>
    </div>

    @if ($pilihanJemput)
        {{-- A-248: barang di proyek dijemput driver dengan SJ tanpa tugas picking. --}}
        @include('shipment.partials.pickup-form', ['judul' => __('Buat SJ jemput'), 'keterangan' => __('satu perjalanan untuk semua baris RET ini; sopir & plat wajib'), 'aksi' => 'buatSjJemput'])
    @endif

    @unless ($portal)
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Pergerakan fisik') }}</strong></div>
            <ul class="list-group list-group-flush small">
                @if ($pck)
                    <li class="list-group-item">{{ __('Tugas picking') }} <a href="{{ route('picks.show', $pck->id) }}">{{ $pck->number }}</a> · {{ $pck->status->label() }}</li>
                @endif
                @if ($ret->returnShipment)
                    <li class="list-group-item">{{ $ret->isPickup() ? __('SJ jemput') : __('SJ balik') }} <a href="{{ route('shipments.show', $ret->return_shipment_id) }}">{{ $ret->returnShipment->number }}</a> · {{ $ret->returnShipment->status->label() }}</li>
                @endif
                @if ($grn)
                    <li class="list-group-item">{{ __('GRN retur') }} <a href="{{ route('receipts.show', $grn->id) }}">{{ $grn->number }}</a> · {{ $grn->status->label() }}</li>
                @endif
                @if (! $pck && ! $ret->returnShipment && ! $grn)
                    <li class="list-group-item text-muted">{{ __('Belum ada pergerakan fisik.') }}</li>
                @endif
            </ul>
        </div>
    @endunless

    @can('sort', $ret)
        <div class="card mb-3 border-primary">
            <div class="card-header"><strong>{{ __('Pemilahan') }}</strong> <span class="text-muted small">— {{ __('setiap baris dipilah seluruhnya; boleh dibagi ke beberapa hasil.') }}</span></div>
            <div class="card-body">
                @foreach ($lines->reject->isSplit()->filter(fn ($l) => (float) $l->qty_received > 0) as $l)
                    <div class="border-bottom pb-2 mb-3" wire:key="pilah-{{ $l->id }}">
                        <div class="mb-2"><strong>{{ $l->item?->code }}</strong> {{ $l->trackingLabel() }} · {{ __('diterima') }} {{ number_format((float) $l->qty_received, 2, ',', '.') }}
                            @if ($l->source() === \App\Domain\Return\Enums\ReturnSource::OnSiteAsset && ($ast = \App\Domain\Asset\Models\AssetHandover::query()->withoutGlobalScopes()->where('goods_return_line_id', $l->id)->latest('id')->first()))
                                · <a href="{{ route('asset-handovers.show', $ast->id) }}">{{ $ast->number }}</a>
                                <span class="badge {{ $ast->status->badge() }}">{{ $ast->status->label() }}</span>
                                @if ($ast->status->value !== 'inspected') <span class="small text-danger">{{ __('periksa aset dulu sebelum dipilah') }}</span> @endif
                            @endif
                        </div>
                        @foreach ($pilah[$l->id] ?? [] as $i => $p)
                            <div class="row g-2 align-items-end mb-1" wire:key="pilah-{{ $l->id }}-{{ $i }}">
                                <div class="col-md-2">
                                    <label class="form-label small">{{ __('Hasil') }} <span class="wajib">*</span></label>
                                    <select class="form-select form-select-sm" wire:model.live="pilah.{{ $l->id }}.{{ $i }}.sorting">
                                        @foreach ($hasilPilah as $nilai => $label)
                                            <option value="{{ $nilai }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">{{ __('Jumlah') }} <span class="wajib">*</span></label>
                                    <input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model="pilah.{{ $l->id }}.{{ $i }}.qty">
                                </div>
                                @if (($p['sorting'] ?? '') !== 'waste')
                                    <div class="col-md-3">
                                        <label class="form-label small">{{ __('Bin tujuan') }} @if (in_array($p['sorting'] ?? '', ['good', 'offcut'], true)) <span class="wajib">*</span> @endif</label>
                                        <select class="form-select form-select-sm" wire:model="pilah.{{ $l->id }}.{{ $i }}.target_bin_id">
                                            <option value="">{{ ($p['sorting'] ?? '') === 'damaged' ? __('Bin Retur (bawaan)') : __('Pilih bin…') }}</option>
                                            @foreach (($p['sorting'] ?? '') === 'damaged' ? $binRusak : $binPenyimpanan as $b)
                                                <option value="{{ $b->id }}">{{ $b->code }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <div class="col-md-3 small text-muted">{{ __('Ke bin Waste') }}</div>
                                @endif
                                @if (in_array($p['sorting'] ?? '', ['damaged', 'waste'], true))
                                    <div class="col-md-3">
                                        <label class="form-label small">{{ __('Alasan') }} <span class="wajib">*</span></label>
                                        <select class="form-select form-select-sm" wire:model="pilah.{{ $l->id }}.{{ $i }}.reason_code_id">
                                            <option value="">{{ __('Pilih alasan…') }}</option>
                                            @foreach (($p['sorting'] ?? '') === 'damaged' ? $alasanRusak : $alasanWaste as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if (($p['sorting'] ?? '') === 'offcut')
                                    <div class="col-md-2">
                                        <label class="form-label small">{{ __('Panjang offcut') }} <span class="wajib">*</span></label>
                                        <input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model="pilah.{{ $l->id }}.{{ $i }}.offcut_length">
                                    </div>
                                @endif
                                @if ($i > 0)
                                    <div class="col-md-1">
                                        <button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusBagian({{ $l->id }}, {{ $i }})" aria-label="{{ __('Hapus bagian') }}">×</button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                        @if (! $l->serial_id && ! $l->piece_id)
                            <button class="btn btn-sm btn-link px-0" type="button" wire:click="tambahBagian({{ $l->id }})">{{ __('+ Bagian') }}</button>
                        @endif
                    </div>
                @endforeach
                @foreach (['sorting', 'qty', 'target_bin_id', 'reason_code_id', 'offcut_length'] as $f)
                    @error('pilah.'.$f) <div class="text-danger small">{{ $message }}</div> @enderror
                @endforeach
            </div>
            <div class="card-footer">
                <button class="btn btn-primary" type="button" wire:click="simpanPilah">{{ __('Simpan pemilahan') }}</button>
            </div>
        </div>
    @endcan

    {{-- A-252: dokumen asal & turunan. --}}
    @unless ($portal ?? false)
        <x-related-documents :document="$ret" />
    @endunless
    @unless ($portal)
        @include('approval.partials.history', ['riwayatApproval' => $riwayatApproval])
    @endunless

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
