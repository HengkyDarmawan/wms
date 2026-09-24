<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $count->number }}
                <span class="badge {{ $count->status->badge() }}">{{ $count->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $count->count_type->label() }}
                · {{ $count->warehouses->pluck('code')->implode(', ') }}
                · {{ $count->freeze_bins ? __('bin dibeku') : __('tanpa pembekuan') }}
                @if ($count->is_audit) · {{ __('sesi audit') }} @endif
                · {{ __('Dibuat') }} {{ $count->creator?->name }}
                @if ($count->started_at) · {{ __('Mulai') }} {{ $count->started_at->format('d/m/Y H:i') }} @endif
                @if ($count->submitter) · {{ __('Rekonsiliasi') }} {{ $count->submitter->name }} @endif
                @if ($count->approver) · {{ __('Disetujui') }} {{ $count->approver->name }} @endif
                @if ($count->lock_date_set) · {{ __('Periode stok dikunci sampai') }} {{ $count->lock_date_set->format('d/m/Y') }} @endif
            </p>
            @if ($count->rejectReason && $count->status->value === 'reconciling')
                <p class="text-danger small mb-0">{{ __('Ditolak approver') }}: {{ $count->rejectReason->label }} — {{ __('perbaiki lalu ajukan ulang.') }}</p>
            @endif
            @if ($count->cancelReason) <p class="text-danger small mb-0">{{ __('Dibatalkan') }}: {{ $count->cancelReason->label }}</p> @endif
        </div>
        <div class="d-flex gap-2">
            @can('report', $count)
                <a class="btn btn-outline-primary" href="{{ route('counts.report', $count) }}">{{ __('Laporan PDF') }}</a>
            @endcan
            <a class="btn btn-outline-secondary" href="{{ route('counts.index') }}">{{ __('Kembali') }}</a>
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
            <div class="card-header"><strong>{{ $dialog === 'tolak' ? __('Tolak hasil opname') : __('Batalkan sesi') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="opn-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="opn-alasan" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($dialog === 'tolak' ? $alasanTolak : $alasanBatal as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="opn-ket">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="opn-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
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
        <div class="card-body d-flex flex-wrap gap-4">
            <div><div class="text-muted small">{{ __('Bin') }}</div><div class="h5 mb-0">{{ $ringkasan['bin'] }}</div></div>
            <div><div class="text-muted small">{{ __('Penugasan selesai') }}</div><div class="h5 mb-0">{{ $ringkasan['selesai'] }} / {{ $ringkasan['tugas'] }}</div></div>
            <div><div class="text-muted small">{{ __('Hitung ulang') }}</div><div class="h5 mb-0">{{ $ringkasan['ulang'] }}</div></div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            @can('start', $count)
                <button class="btn btn-primary" type="button" wire:click="mulai">{{ __('Mulai sesi') }}</button>
            @endcan
            @can('reconcile', $count)
                <button class="btn btn-primary" type="button" wire:click="rekonsiliasi">
                    {{ $count->status->value === 'reconciling' ? __('Ajukan ulang ke approval') : __('Rekonsiliasi & ajukan approval') }}
                </button>
            @endcan
            @can('approve', $count)
                <button class="btn btn-success" type="button" wire:click="setujui">{{ __('Setujui hasil') }}</button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">{{ __('Tolak') }}</button>
            @endcan
            @can('cancel', $count)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">{{ __('Batalkan') }}</button>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Penugasan penghitung') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Bin') }}</th>
                        <th scope="col">{{ __('Putaran') }}</th>
                        <th scope="col">{{ __('Penghitung') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $a)
                        <tr wire:key="tugas-{{ $a->id }}">
                            <td>{{ $a->bin?->code }} @if ($a->bin?->count_flag) <span class="badge text-bg-warning">⚑</span> @endif</td>
                            <td>{{ $a->round === 2 ? __('Hitung ulang') : __('Pertama') }}</td>
                            <td>
                                @if ($counters->isNotEmpty() && ! $a->isDone())
                                    <div class="input-group input-group-sm">
                                        <select class="form-select" wire:model="penghitung.{{ $a->id }}">
                                            <option value="">{{ $a->counter?->name ?? __('— belum ditugaskan —') }}</option>
                                            @foreach ($counters as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-outline-primary" type="button" wire:click="tugaskan({{ $a->id }})">{{ __('Tetapkan') }}</button>
                                    </div>
                                @else
                                    {{ $a->counter?->name ?? __('— belum ditugaskan —') }}
                                @endif
                            </td>
                            <td><span class="badge {{ $a->status->badge() }}">{{ $a->status->label() }}</span></td>
                            <td>
                                @if ((int) $a->counter_user_id === (int) auth()->id() && ! $a->isDone() && $count->status->isCounting())
                                    <a class="btn btn-sm btn-primary" href="{{ route('count-tasks.show', $a) }}">{{ __('Hitung') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-4" colspan="5">{{ __('Penugasan dibuat saat sesi dimulai.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <strong>{{ __('Rekonsiliasi') }}</strong>
            <span class="small text-muted">{{ __('Kecil ≤ ambang relatif & absolut · Sedang ≤ 5 % (hitung ulang) · Besar: akar masalah wajib') }}</span>
        </div>
        @if (! $bolehLihat)
            <div class="card-body text-muted">{{ __('Hitung buta: angka sistem dan hasil hitung disembunyikan selama Anda masih punya penugasan hitung di sesi ini, atau sampai sesi masuk rekonsiliasi.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Bin') }}</th>
                            <th scope="col">{{ __('Item') }}</th>
                            <th scope="col">{{ __('Kondisi') }}</th>
                            <th class="text-end" scope="col">{{ __('Sistem') }}</th>
                            <th class="text-end" scope="col">{{ __('Hitung 1') }}</th>
                            <th class="text-end" scope="col">{{ __('Hitung 2') }}</th>
                            <th class="text-end" scope="col">{{ __('Selisih') }}</th>
                            <th scope="col">{{ __('Kelas') }}</th>
                            <th scope="col">{{ __('Akar masalah') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lines as $l)
                            <tr wire:key="baris-{{ $l->id }}">
                                <td>{{ $l->bin?->code }}</td>
                                <td>
                                    {{ $l->item?->code }}
                                    <div class="small text-muted">{{ $l->trackingLabel() ?: $l->item?->name }} @if ($l->is_unexpected) · {{ __('temuan') }} @endif</div>
                                </td>
                                <td><span class="badge text-bg-{{ $l->stock_status->badge() }}">{{ $l->stock_status->label() }}</span></td>
                                <td class="text-end">{{ number_format((float) $l->system_qty, 2, ',', '.') }}</td>
                                <td class="text-end">{{ $l->counted_qty_r1 !== null ? number_format((float) $l->counted_qty_r1, 2, ',', '.') : '—' }}</td>
                                <td class="text-end">{{ $l->counted_qty_r2 !== null ? number_format((float) $l->counted_qty_r2, 2, ',', '.') : ($l->is_recount ? '…' : '') }}</td>
                                <td class="text-end">
                                    {{ $l->variance_qty !== null ? number_format((float) $l->variance_qty, 2, ',', '.') : '—' }}
                                    @if ($l->variance_pct !== null && $l->hasVariance()) <div class="small text-muted">{{ number_format((float) $l->variance_pct, 2, ',', '.') }} %</div> @endif
                                </td>
                                <td>@if ($l->variance_class) <span class="badge {{ $l->variance_class->badge() }}">{{ $l->variance_class->label() }}</span> @endif</td>
                                <td style="min-width: 16rem">
                                    @if ($l->variance_class && $bisaUbahAkar)
                                        <div class="input-group input-group-sm">
                                            <select class="form-select" wire:model="akar.{{ $l->id }}.root_cause">
                                                <option value="">{{ $l->variance_class->value === 'major' ? __('Pilih… *') : __('—') }}</option>
                                                @foreach ($akarOptions as $nilai => $label)
                                                    <option value="{{ $nilai }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-outline-primary" type="button" wire:click="simpanAkar({{ $l->id }})">{{ __('Simpan') }}</button>
                                        </div>
                                        <input class="form-control form-control-sm mt-1" type="text" wire:model="akar.{{ $l->id }}.note" placeholder="{{ __('Catatan (opsional)') }}">
                                    @else
                                        {{ $l->root_cause?->label() }} @if ($l->note) <div class="small text-muted">{{ $l->note }}</div> @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4" colspan="9">{{ __('Belum ada baris hitung.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($adjustments->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Penyesuaian stok dari sesi ini') }}</strong></div>
            <ul class="list-group list-group-flush">
                @foreach ($adjustments as $adj)
                    <li class="list-group-item d-flex justify-content-between">
                        @can('view', $adj)
                            <a href="{{ route('adjustments.show', $adj) }}">{{ $adj->number }}</a>
                        @else
                            <span>{{ $adj->number }}</span>
                        @endcan
                        <span>{{ $adj->warehouse?->code }} <span class="badge {{ $adj->status->badge() }}">{{ $adj->status->label() }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('approval.partials.history', ['riwayatApproval' => $riwayatApproval])

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
